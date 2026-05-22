<?php

namespace App\Services\Approval;

use App\Models\ApprovalRequest;
use App\Models\Leave;
use App\Services\LeaveBalanceService;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(private LeaveBalanceService $leaveBalanceService) {}

    public function approve(ApprovalRequest $approvalRequest, int $actorId, ?string $comment): void
    {
        DB::transaction(function () use ($approvalRequest, $actorId, $comment) {
            $meta       = $approvalRequest->metadata ?? [];
            $approvable = $approvalRequest->approvable;

            if ($approvable instanceof Leave && !empty($meta['batch_id'])) {
                $this->approveBatch($meta['batch_id'], $actorId, $comment);
                return;
            }

            $approvalRequest->actions()->create([
                'actor_id' => $actorId,
                'action'   => 'approved',
                'comment'  => $comment,
            ]);
            $approvalRequest->update(['current_state' => 'approved']);
            $approvable->update(['status' => 'approved']);

            if (method_exists($approvable, 'applyDomainEffect')) {
                $approvable->applyDomainEffect();
            }

            if ($approvable instanceof Leave) {
                $approvable->update(['approved_by' => $actorId]);
                $this->leaveBalanceService->consume($approvable);
            }
        });
    }

    public function deny(ApprovalRequest $approvalRequest, int $actorId, ?string $comment): void
    {
        DB::transaction(function () use ($approvalRequest, $actorId, $comment) {
            $meta       = $approvalRequest->metadata ?? [];
            $approvable = $approvalRequest->approvable;

            if ($approvable instanceof Leave && !empty($meta['batch_id'])) {
                $this->denyBatch($meta['batch_id'], $actorId, $comment);
                return;
            }

            $approvalRequest->actions()->create([
                'actor_id' => $actorId,
                'action'   => 'rejected',
                'comment'  => $comment,
            ]);
            $approvalRequest->update(['current_state' => 'rejected']);
            $approvalRequest->approvable->update(['status' => 'rejected']);
        });
    }

    private function approveBatch(string $batchId, int $actorId, ?string $comment): void
    {
        $allRequests = ApprovalRequest::query()
            ->where('approvable_type', Leave::class)
            ->where('metadata->batch_id', $batchId)
            ->with('approvable')
            ->get();

        foreach ($allRequests as $req) {
            $leave = $req->approvable;
            if (!$leave instanceof Leave) {
                continue;
            }

            $req->actions()->create(['actor_id' => $actorId, 'action' => 'approved', 'comment' => $comment]);
            $req->update(['current_state' => 'approved']);
            $leave->update(['status' => 'approved', 'approved_by' => $actorId]);
            $this->leaveBalanceService->consume($leave);
        }
    }

    private function denyBatch(string $batchId, int $actorId, ?string $comment): void
    {
        $allRequests = ApprovalRequest::query()
            ->where('approvable_type', Leave::class)
            ->where('metadata->batch_id', $batchId)
            ->with('approvable')
            ->get();

        foreach ($allRequests as $req) {
            $leave = $req->approvable;
            if (!$leave instanceof Leave) {
                continue;
            }

            $req->actions()->create(['actor_id' => $actorId, 'action' => 'rejected', 'comment' => $comment]);
            $req->update(['current_state' => 'rejected']);
            $leave->update(['status' => 'rejected']);
        }
    }
}
