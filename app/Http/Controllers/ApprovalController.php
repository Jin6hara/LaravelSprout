<?php

namespace App\Http\Controllers;

use App\Models\ApprovalRequest;
use App\Models\Leave;
use App\Services\Approval\ApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    public function __construct(private ApprovalService $approvalService) {}

    /**
     * 承認リクエスト詳細表示
     * Leave の場合は種別（paid/special）に応じた日付サマリを生成してビューに渡す
     */
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

    /**
     * 承認処理
     * Leave はバッチIDに紐づく全リクエストを一括承認、その他は単体承認
     */
    public function approve(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->authorize('act', $approvalRequest);

        $this->approvalService->approve($approvalRequest, Auth::id(), $request->input('comment'));

        return back()->with('toast', '承認しました。');
    }

    /**
     * 却下処理
     * Leave はバッチIDに紐づく全リクエストを一括却下、その他は単体却下
     */
    public function deny(Request $request, ApprovalRequest $approvalRequest)
    {
        $this->authorize('act', $approvalRequest);

        $this->approvalService->deny($approvalRequest, Auth::id(), $request->input('comment'));

        return back()->with('toast', '却下しました。');
    }
}
