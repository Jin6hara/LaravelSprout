<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\LeaveBalanceService;
use App\Policies\ApprovalRequestPolicy;
use App\Models\Leave;

class ApprovalController extends Controller
{
    public function show(ApprovalRequest $approvalRequest)
    {
        $this->authorize('view', $approvalRequest);
        return view('approvals.show', compact('approvalRequest'));
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->authorize('act', $approvalRequest);

        DB::transaction(function () use ($approvalRequest) {
            // 1) アクション履歴
            $approvalRequest->actions()->create([
                'actor_id' => Auth::id(),
                'action'   => 'approved',
                'comment'  => request('comment'),
            ]);

            // 2) 承認リクエスト状態更新
            $approvalRequest->update(['current_state' => 'approved']);

            // 3) ドメイン反映（morph先に委譲）
            $approvable = $approvalRequest->approvable; // e.g. RoleChange
            $approvable->update(['status' => 'approved']);

            // 4) 実ロール変更（RoleChangeの場合）
            if (method_exists($approvable, 'applyDomainEffect')) {
                $approvable->applyDomainEffect(); // 後述の小メソッド
            }

            /** @var Leave $leave */
            if ($approvable instanceof Leave) {
                $approvable->update([
                    // 'status' は上で更新済み
                    'approved_by' => auth()->id(),
                ]);

                app(LeaveBalanceService::class)
                    ->consume($approvable);
            }
        });

        return back()->with('status', '承認しました。');
    }

    public function deny(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->authorize('act', $approvalRequest);

        DB::transaction(function () use ($approvalRequest) {
            $approvalRequest->actions()->create([
                'actor_id' => Auth::id(),
                'action'   => 'denied',
                'comment'  => request('comment'),
            ]);

            $approvalRequest->update(['current_state' => 'denied']);

            $approvable = $approvalRequest->approvable;
            $approvable->update(['status' => 'denied']);
        });

        return back()->with('status', '却下しました。');
    }
}
