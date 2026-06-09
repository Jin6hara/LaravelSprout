<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Services\Notifications\ScopedNotificationService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function __construct(private ScopedNotificationService $scopedNotifications) {}

    /**
     * 通知一覧画面
     * GET /notifications — 最新100件を「承認待ち」と「処理済み」に分けて表示。各承認申請の現在の状態も付与
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $all = $this->scopedNotifications->visibleNotifications($user);

        // 通知に含まれる approval_request_id を一括取得
        $arIds = $all->map(fn ($n) => $n->data['approval_request_id'] ?? null)
            ->filter()->unique()->values();

        // id => current_state のマップ: pending|approved|denied
        $arStateById = ApprovalRequest::whereIn('id', $arIds)
            ->pluck('current_state', 'id');

        // 通知をデコレート（is_pending / is_unread）
        $decorated = $all->map(function ($n) use ($arStateById) {
            $data = $n->data ?? [];
            $arId = $data['approval_request_id'] ?? null;
            $state = $arId ? ($arStateById[$arId] ?? null) : null;

            // 未承認の定義：explicit に pending。state 不明（別種の通知）は上側に寄せる運用
            $isPending = ($state === 'pending') || is_null($state);
            $isUnread = is_null($n->read_at);

            // Blade で使いやすいよう動的プロパティ付与
            $n->computed_state = $state;     // 'pending' | 'approved' | 'denied' | null
            $n->is_pending = $isPending; // 上
            $n->is_unread = $isUnread;

            return $n;
        });

        // 上（未承認）グループ：未読を先に、同順なら新しい順
        $top = $decorated->where('is_pending', true)
            ->sortBy([
                fn ($n) => $n->is_unread ? 0 : 1,
                fn ($n) => -$n->created_at->getTimestamp(),
            ])->values();

        // 下（承認済み/却下済み）グループ：未読を先に、同順なら新しい順
        $bottom = $decorated->where('is_pending', false)
            ->sortBy([
                fn ($n) => $n->is_unread ? 0 : 1,
                fn ($n) => -$n->created_at->getTimestamp(),
            ])->values();

        return view('notifications.index', [
            'topNotifications' => $top,
            'bottomNotifications' => $bottom,
        ]);
    }

    /**
     * 指定通知を既読にする
     * POST /notifications/{notification}/read — 自分の通知のみ操作可（ポリシーで検証）
     */
    public function markAsRead(Request $request, DatabaseNotification $notification)
    {
        $this->authorize('view-notification', $notification);
        $notification->markAsRead();

        return back();
    }

    /**
     * 全通知を既読にする
     * POST /notifications/read-all — ログインユーザーの全未読通知を一括で既読化
     */
    public function markAllAsRead(Request $request)
    {
        $this->scopedNotifications->markVisibleUnreadAsRead($request->user());

        return back();
    }

    /**
     * 通知の詳細ページへ遷移（自動既読）
     * GET /notifications/{notification}/go — 未読なら既読化した上で data['url'] へリダイレクト
     */
    public function go(Request $request, DatabaseNotification $notification)
    {
        $this->authorize('view-notification', $notification);

        // data['url'] に詳細ページのURLを入れてある前提
        $url = $notification->data['url'] ?? route('notifications.index');

        // 未読なら既読化
        if (is_null($notification->read_at)) {
            $notification->markAsRead();
        }

        // 目的地へリダイレクト
        return redirect()->to($url);
    }
}
