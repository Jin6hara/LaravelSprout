<?php

namespace App\Services\Leave;

use App\Enums\LeaveStatus;
use App\Models\Leave;
use App\Services\LeaveBalanceService;
use Illuminate\Support\Facades\DB;

class CancelLeaveService
{
    public function handle(Leave $leave): void
    {
        DB::transaction(function () use ($leave) {
            $wasApproved = $leave->status === LeaveStatus::Approved->value;

            $leave->update(['status' => LeaveStatus::Cancelled->value]);

            if ($wasApproved) {
                app(LeaveBalanceService::class)->revert($leave);
            }
        });
    }
}
