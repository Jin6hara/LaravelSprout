<?php

namespace App\Observers;

use App\Models\Leave;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

class LeaveObserver
{
    public function created(Leave $leave): void
    {
        // 欠席のみ「正規コマのコピー」を発生させる
        if ($leave->kind !== 'absence') return;

        foreach ($leave->eachDate() as $d) {
            $dateStr = $d->toDateString();
            $dow     = $d->dayOfWeek; // 0=Sun ... 6=Sat

            // user_schedule_assignments 経由でユーザーの正規コマを引く
            $lines = DB::table('schedule_lines as sl')
                ->join('user_schedule_assignments as usa', 'usa.schedule_id', '=', 'sl.schedule_id')
                ->where('usa.user_id', $leave->user_id)
                // その日がアサイン期間内
                ->whereDate('usa.start_date', '<=', $dateStr)
                ->where(function ($q) use ($dateStr) {
                    $q->whereNull('usa.end_date')
                        ->orWhereDate('usa.end_date', '>=', $dateStr);
                })
                // その日の DOW が一致
                ->where('sl.dow', $dow)
                // その日が line の有効期間内
                ->whereDate('sl.effective_start', '<=', $dateStr)
                ->where(function ($q) use ($dateStr) {
                    $q->whereNull('sl.effective_end')
                        ->orWhereDate('sl.effective_end', '>=', $dateStr);
                })
                ->select([
                    'sl.id as line_id',
                    'sl.school_name',
                    'sl.start_time',
                    'sl.end_time',
                ])
                ->get();

            foreach ($lines as $line) {
                // 欠席により、その日の正規コマを EVENT（draft）としてコピー
                Event::firstOrCreate(
                    [
                        'event_date'              => $dateStr,
                        'source_schedule_line_id' => $line->line_id,
                        // 担当未確定（代行が決まれば assigned_user_id を別フローで更新）
                        'assigned_user_id'        => null,
                    ],
                    [
                        'title'            => null,
                        'school_name'      => $line->school_name,
                        'start_time'       => $line->start_time,
                        'end_time'         => $line->end_time,
                        'kind'             => 'regular_copy',
                        'original_user_id' => $leave->user_id, // もともとの担当
                        'status'           => 'draft',
                        'notes'            => '欠席により正規コマをコピー',
                    ]
                );
            }
        }
    }
}
