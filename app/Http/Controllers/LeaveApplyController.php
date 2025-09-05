<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveApplyRequest;
use App\Models\User;
use App\Models\Leave;
use App\Models\ApprovalRequest;
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class LeaveApplyController extends Controller
{
    // 本人
    public function create()
    {
        return view('leaves.alpApply', [
            'action' => route('leave.apply.store'),
        ]);
    }

    public function store(LeaveApplyRequest $request)
    {
        return $this->applyCore(
            (int)$request->input('user_id'),
            $request->date('start_date')->toDateString(),
            $request->input('end_date') ? $request->date('end_date')->toDateString() : null,
            $request->input('reason'),
            auth()->id() // requested_by
        );
    }

    // 管理者：指定ユーザー
    public function createForUser(User $user)
    {
        return view('leaves.alpApply', [
            'action' => route('leave.apply.storeForUser', $user),
            'targetUser' => $user,
        ]);
    }

    public function storeForUser(LeaveApplyRequest $request, User $user)
    {
        $this->authorize('manage', $user); // 任意（Policyあれば）
        return $this->applyCore(
            $user->id,
            $request->date('start_date')->toDateString(),
            $request->input('end_date') ? $request->date('end_date')->toDateString() : null,
            $request->input('reason'),
            auth()->id()
        );
    }

    private function applyCore(int $userId, string $start, ?string $end, ?string $reason, int $requestedByUserId)
    {
        DB::transaction(function () use ($userId, $start, $end, $reason, $requestedByUserId) {
            // 1) Leave は "pending" で作成（kind=paid 固定／変更可）
            $leave = Leave::create([
                'user_id'    => $userId,
                'start_date' => $start,
                'end_date'   => $end,
                'kind'       => 'paid',
                'excused'    => 'unknown',
                'reason'     => $reason,
                'status'     => 'pending',  // ← Observerはapprovedのみスナップショット
            ]);

            // 2) 承認リクエスト作成（ポリモーフィック紐付け）
            $ar = $leave->approvalRequest()->create([
                'title'            => sprintf('有給申請: user#%d %s〜%s', $userId, $start, $end ?? $start),
                'requested_by_id'  => $requestedByUserId,
                'current_state'    => 'pending',
                'metadata'         => [
                    'leave_id'   => $leave->id,
                    'user_id'    => $userId,
                    'start_date' => $start,
                    'end_date'   => $end,
                    'kind'       => 'paid',
                ],
            ]);

            // 3) 通知（管理者以上へ）
            $admins = User::role(['admin', 'super_admin'])->get();
            Notification::send($admins, new ApprovalRequestedNotification($ar));
        });

        return redirect()->route('notifications.index')
            ->with('success', '有給申請を送信しました。承認をお待ちください。');
    }
}
