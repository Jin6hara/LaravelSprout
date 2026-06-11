<?php
/**
 * 休暇・シフトなどの承認リクエストの詳細表示・承認・却下操作を担うコントローラ。
 */
namespace App\Http\Controllers;

use App\Enums\LeaveKind;
use App\Models\ApprovalRequest;
use App\Models\Leave;
use App\Services\Approval\ApprovalService;
use App\Services\Leave\GeneratedShiftDecisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    public function __construct(
        private ApprovalService $approvalService,
        private GeneratedShiftDecisionService $generatedShiftDecision
    ) {}

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
        $generatedShiftDetails = collect();

        if ($approvable instanceof Leave) {
            $kind = $meta['kind'] ?? $approvable->kind ?? null;

            if ($kind === LeaveKind::Special->value) {
                // ★ 特別休暇 → 期間表示（from〜to）
                $from = $meta['date_from'] ?? optional($approvable->start_date)->format('Y-m-d');
                $to   = $meta['date_to']   ?? optional($approvable->end_date)->format('Y-m-d');

                if ($from && $to) {
                    $dateSummary = "{$from}〜{$to}";
                } else {
                    $dateSummary = $from ?: $to ?: '-';
                }
            } elseif ($kind === LeaveKind::Paid->value) {
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

            $generatedShiftDetails = $this->generatedShiftDecision->detailsForApprovalRequest($approvalRequest);
        }

        return view('approvals.show', [
            'approvalRequest'       => $approvalRequest,
            'dateSummary'           => $dateSummary,
            'generatedShiftDetails' => $generatedShiftDetails,
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

        $generatedShiftAction = $this->generatedShiftActionFromRequest($request);
        $generatedShiftCount = $this->generatedShiftDecision->detailsForApprovalRequest($approvalRequest)->count();

        if ($generatedShiftCount > 0 && $this->generatedShiftDecision->normalizeAction($generatedShiftAction) === null) {
            return back()->with('toast_errors', [
                'Please confirm what to do with generated shift(s) before denying this leave request.',
            ]);
        }

        $this->approvalService->deny(
            $approvalRequest,
            Auth::id(),
            $request->input('comment'),
            $generatedShiftAction
        );

        return back()->with('toast', '却下しました。');
    }

    private function generatedShiftActionFromRequest(Request $request): ?string
    {
        return $request->input('generated_shift_action')
            ?? $request->input('inactive_generated_shift_action');
    }
}
