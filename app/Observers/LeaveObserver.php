<?php

namespace App\Observers;

use App\Models\Leave;
use App\Services\Calendar\LeaveSnapshotService;
use Illuminate\Support\Facades\DB;

class LeaveObserver
{
    public function created(Leave $leave): void
    {
        // トランザクション確定後にスナップショット生成（新規は無条件でOK）
        DB::afterCommit(fn() => app(LeaveSnapshotService::class)->rebuildSnapshotsForLeave($leave));
    }

    public function updated(Leave $leave): void
    {
        if (!$leave->wasChanged()) {
            return;
        }
        DB::afterCommit(fn() => app(LeaveSnapshotService::class)->rebuildSnapshotsForLeave($leave));
    }

    // onCascade delete あるため原則不要。
    // 将来DB連鎖では消せない他テーブルを削除、削除時に通知・ログ記録等を行う場合に利用。
    public function deleted(Leave $leave): void
    {
        DB::afterCommit(fn() => app(LeaveSnapshotService::class)->deleteSnapshotsForLeave($leave));
    }
}
