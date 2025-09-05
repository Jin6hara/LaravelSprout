<?php

// app/Observers/LeaveObserver.php //以前の内容をLeaveSnapShotに分けた？
namespace App\Observers;

use App\Models\Leave;
use App\Services\Calendar\LeaveSnapshotService;

class LeaveObserver
{
    public function created(Leave $leave): void
    {
        app(LeaveSnapshotService::class)->rebuildSnapshotsForLeave($leave);
    }

    public function updated(Leave $leave): void
    {
        // 期間やstatusが変わった場合も安全に作り直す
        app(LeaveSnapshotService::class)->rebuildSnapshotsForLeave($leave);
    }

    public function deleted(Leave $leave): void
    {
        // 紐づく regular_copy を削除
        app(LeaveSnapshotService::class)->deleteSnapshotsForLeave($leave);
    }
}
