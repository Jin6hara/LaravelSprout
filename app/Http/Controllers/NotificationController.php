<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use App\Models\ApprovalRequest;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // 最新100件を対象（必要に応じて増減）
        $all = $user->notifications()->latest()->take(100)->get();

        // 通知に含まれる approval_request_id を一括取得
        $arIds = $all->map(fn($n) => $n->data['approval_request_id'] ?? null)
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
            $isUnread  = is_null($n->read_at);

            // Blade で使いやすいよう動的プロパティ付与
            $n->computed_state = $state;     // 'pending' | 'approved' | 'denied' | null
            $n->is_pending     = $isPending; // 上
            $n->is_unread      = $isUnread;

            return $n;
        });

        // 上（未承認）グループ：未読を先に、同順なら新しい順
        $top = $decorated->where('is_pending', true)
            ->sortBy([
                fn($n) => $n->is_unread ? 0 : 1,
                fn($n) => -$n->created_at->getTimestamp(),
            ])->values();

        // 下（承認済み/却下済み）グループ：未読を先に、同順なら新しい順
        $bottom = $decorated->where('is_pending', false)
            ->sortBy([
                fn($n) => $n->is_unread ? 0 : 1,
                fn($n) => -$n->created_at->getTimestamp(),
            ])->values();

        return view('notifications.index', [
            'topNotifications'    => $top,
            'bottomNotifications' => $bottom,
        ]);
    }

    public function markAsRead(Request $request, DatabaseNotification $notification)
    {
        abort_unless($notification->notifiable_id === $request->user()->id, 403);
        $notification->markAsRead();
        return back();
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back();
    }

    // 詳細へ進む中継（自動既読）
    public function go(Request $request, DatabaseNotification $notification)
    {
        // 自分の通知だけ許可
        abort_unless($notification->notifiable_id === $request->user()->id, 403);

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
