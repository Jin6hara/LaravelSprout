<?php

namespace App\Services\Leave;

use App\Enums\LeaveStatus;
use App\Models\Leave;
use App\Services\LeaveBalanceService;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;

class CancelLeaveService
{
    public function __construct(
        private GeneratedShiftDecisionService $generatedShiftDecision,
        private LeaveBalanceService $leaveBalanceService
    ) {}

    public function handle(Leave $leave, ?string $generatedShiftAction = null): void
    {
        DB::transaction(function () use ($leave, $generatedShiftAction) {
            $wasApproved = $leave->status === LeaveStatus::Approved->value;
            $normalizedAction = $this->generatedShiftDecision->normalizeAction($generatedShiftAction);

            if ($this->generatedShiftDecision->hasGeneratedShifts($leave)) {
                if (!$normalizedAction) {
                    throw new InvalidArgumentException('Generated shift action is required before cancelling this leave.');
                }

                $this->generatedShiftDecision->applyAction($leave, $normalizedAction);
            }

            $leave->generatedShiftAction = $normalizedAction;
            $leave->update(['status' => LeaveStatus::Cancelled->value]);

            if ($wasApproved) {
                $this->leaveBalanceService->revert($leave);
            }
        });
    }
}
