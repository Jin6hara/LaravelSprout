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

        $meta       = $approvalRequest->metadata ?? [];
        $approvable = $approvalRequest->approvable;
        $dateSummary = null;

        if ($approvable instanceof Leave) {
            $kind = $meta['kind'] ?? $approvable->kind ?? null;

            if ($kind === 'special') {
                // ★ 特別休暇 → 期間表示（from〜to）
                $from = $meta['date_from'] ?? optional($approvable->start_date)->format('Y-m-d');
                $to   = $meta['date_to']   ?? optional($approvable->end_date)->format('Y-m-d');

                if ($from && $to) {
                    $dateSummary = "{$from}〜{$to}";
                } else {
                    $dateSummary = $from ?: $to ?: '-';
                }
            } elseif ($kind === 'paid') {
                // ★ 有給 → 同じバッチの全日を「日, 日, 日」で表示
                $batchId = $meta['batch_id'] ?? null;

                if ($batchId) {
                    $dates = ApprovalRequest::query()
                        ->where('approvable_type', Leave::class)
                        ->where('metadata->batch_id', $batchId)
                        ->orderBy('metadata->date')
                        ->pluck('metadata')
                        ->map(fn($m) => $m['date'] ?? null)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    if (!empty($dates)) {
                        $dateSummary = implode(', ', $dates);
                    }
                }

                // バッチIDが無い/うまく取れない場合のフォールバック
                if (!$dateSummary) {
                    $dateSummary = $meta['date'] ?? optional($approvable->start_date)->format('Y-m-d') ?? '-';
                }
            }
        }

        return view('approvals.show', [
            'approvalRequest' => $approvalRequest,
            'dateSummary'     => $dateSummary,
        ]);
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

        return back()->with('toast', '承認しました。');
    }

    public function deny(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->authorize('act', $approvalRequest);

        DB::transaction(function () use ($approvalRequest) {
            $approvalRequest->actions()->create([
                'actor_id' => Auth::id(),
                'action'   => 'rejected',
                'comment'  => request('comment'),
            ]);

            $approvalRequest->update(['current_state' => 'rejected']);

            $approvable = $approvalRequest->approvable;
            $approvable->update(['status' => 'rejected']);
        });

        return back()->with('toast', '却下しました。');
    }
}
