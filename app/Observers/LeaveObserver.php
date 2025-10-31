<?php

namespace App\Observers;

use App\Models\Leave;
use App\Services\Calendar\LeaveSnapshotService;
use Illuminate\Support\Facades\DB;

class LeaveObserver
{
    public function created(Leave $leave): void
    {
        app(LeaveSnapshotService::class)->rebuildSnapshotsForLeave($leave);
    }

    public function updated(Leave $leave): void
    {
        if (!$leave->wasChanged()) {
            return;
        }
        DB::afterCommit(fn() => app(LeaveSnapshotService::class)->rebuildSnapshotsForLeave($leave));
    }

    public function deleted(Leave $leave): void
    {
        // 紐づく regular_copy を削除
        app(LeaveSnapshotService::class)->deleteSnapshotsForLeave($leave);
    }
}
