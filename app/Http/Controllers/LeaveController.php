<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\Leave;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveController extends Controller
{
    public function create()
    {
        $user = auth()->user();
        return view('leaves.create', [
            'defaultUserId' => $user->id,
        ]);
    }

    public function store(StoreLeaveRequest $request)
    {
        // 承認フローがある場合は pending から始めるなど調整してください
        $leave = Leave::create([
            'user_id'     => (int)$request->input('user_id'),
            'start_date'  => $request->date('start_date'),
            'end_date'    => $request->input('end_date') ? $request->date('end_date') : null,
            'kind'        => $request->input('kind'),
            'excused'     => $request->input('excused', 'unexcused'),
            'special_type' => $request->input('special_type'),
            'reason'      => $request->input('reason'),
            'time_start'  => $request->input('time_start'),
            'time_end'    => $request->input('time_end'),
            'status'      => $request->input('status', 'approved'),
            'approved_by' => auth()->id(), // 簡易に自分で承認した体
        ]);

        // 作成日のカレンダー画面に遷移
        $date = optional($leave->start_date)->format('Y-m-d');

        // Observer が自動でスナップショット生成
        return redirect()->to(route('calendar.edit') . '?event_date=' . urlencode($date))
            ->with('status', '欠席登録成功。通常シフトある場合はEventが作成されます。');
    }


    public function cancel(Leave $leave)
    {
        $this->authorize('cancel', $leave);

        DB::transaction(function () use ($leave) {
            $wasApproved = $leave->status === 'approved';

            $leave->update(['status' => 'cancelled']);

            if ($wasApproved) {
                app(LeaveBalanceService::class)->revert($leave);
            }
        });

        return back()->with('success', '申請を取り消しました。');
    }

    /**
     * 欠席報告画面
     * - 一般ユーザー：自分のみ
     * - 管理者(admin|super_admin)：誰のページでも閲覧可能（そのユーザーのデータのみ表示）
     */
    public function absence(Request $request, User $user)
    {
        $viewer = Auth::user();
        $isAdmin = $viewer->hasRole(['admin', 'super_admin']);
        if (!$isAdmin && $viewer->id !== $user->id) {
            abort(403);
        }

        // そのユーザーの leave をすべて表示（必要なら期間フィルタを追加）
        $leaves = Leave::query()
            ->where('user_id', $user->id)
            ->whereIn('kind', ['absence', 'absense_to_paid', 'other'])
            ->orderByDesc('start_date')
            ->get();

        // 表示名マッピング（ご指定）
        $kindLabels = [
            'absence'          => 'Unpaid Leave',
            'absense_to_paid'  => 'ALP',     // ※ご指定のスペルに合わせています
            'other'            => 'Others',
        ];

        // Handle Type はセレクト式（必要に応じて差し替え）
        $handleTypeOptions = [
            'self_cover' => 'Self Cover',
            'makeup'     => 'Make-up Lesson',
            'refund'     => 'Refund',
            'other'      => 'Other',
        ];

        return view('calendar.absenceReport', compact('user', 'leaves', 'kindLabels', 'handleTypeOptions'));
    }

    /**
     * 欠席の自己報告（Reason + Handle Type）
     * - 対象：kind=absence かつ reason/handle_type が両方 null のもの
     * - 送信後は編集不可（＝両方値が入ったらSubmittedとみなす）
     */
    public function report(Request $request, Leave $leave)
    {
        $viewer = Auth::user();
        $isAdmin = $viewer->hasRole(['admin', 'super_admin']);
        if (!$isAdmin && $viewer->id !== $leave->user_id) {
            abort(403);
        }

        // ルール：kind=absence、かつ 両方null のときのみ提出可
        if ($leave->kind !== 'absence') {
            return back()->with('error', 'This leave is not target for absence self-report.');
        }
        if (!is_null($leave->reason) || !is_null($leave->handle_type)) {
            return back()->with('error', 'Already submitted.');
        }

        // バリデーション
        $validated = $request->validate([
            'reason'       => ['required', 'string', 'max:1000'],
            'handle_type'  => ['required', 'in:self_cover,makeup,refund,other'],
        ]);

        // 保存（両方が埋まったら Submitted として扱う）
        $leave->reason = $validated['reason'];
        $leave->handle_type = $validated['handle_type'];
        $leave->save();

        return back()->with('success', 'Submitted.');
    }
}
