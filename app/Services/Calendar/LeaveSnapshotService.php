<?php
// app/Services/Calendar/LeaveSnapshotService.php
namespace App\Services\Calendar;

use App\Models\Event;
use App\Models\EventDetail;
use App\Models\Leave;
use App\Models\Lesson;
use App\Models\ScheduleLine;
use App\Models\UserScheduleAssignment;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LeaveSnapshotService
{
    /**
     * Leaveに紐づくスナップショットを再生成
     */
    public function rebuildSnapshotsForLeave(Leave $leave): void
    {
        DB::transaction(function () use ($leave) {
            // まず既存のスナップショットを消す（冪等性）
            $this->deleteSnapshotsForLeave($leave);

            // 承認済みのみ生成（ポリシーは要件に合わせて）
            if ($leave->status !== 'approved') return;

            // 期間生成（end_date が null の場合は単日）
            $start = Carbon::parse($leave->start_date);
            $end   = $leave->end_date ? Carbon::parse($leave->end_date) : Carbon::parse($leave->start_date);
            $period = CarbonPeriod::create($start, $end);

            foreach ($period as $date) {
                $this->snapshotDay($leave, $date);
            }
        });
    }

    /**
     * Leaveに紐づく'snapshot'を削除
     */
    public function deleteSnapshotsForLeave(Leave $leave): void
    {
        DB::transaction(function () use ($leave) {
            Event::query()
                ->where('source_leave_id', $leave->id)
                ->delete();
        });
    }

    /**
     * 特定日のスナップショット生成
     */
    private function snapshotDay(Leave $leave, Carbon $date): void
    {
        $userId = $leave->user_id;
        $dow    = $date->dayOfWeek; // 0..6
        $ymd    = $date->toDateString();

        // その日有効な割当（end_date null も許容）
        $asg = UserScheduleAssignment::with(['schedule.lines.details'])
            ->where('user_id', $userId)
            ->whereDate('start_date', '<=', $ymd)
            ->where(function ($q) use ($ymd) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $ymd);
            })
            ->first();

        if (!$asg || !$asg->schedule) {
            return; // 割当なし → スナップショット対象なし
        }

        /** @var Collection<int,\App\Models\ScheduleLine> $lines */
        $lines = $asg->schedule->lines
            ->filter(function ($ln) use ($dow, $ymd) {
                // DOW一致 + effective_start <= 日付 <= (effective_end or null=無期限)
                $okDow = (int)$ln->dow === (int)$dow;
                $okStart = $ln->effective_start && $ln->effective_start->toDateString() <= $ymd;
                $okEnd   = is_null($ln->effective_end) || $ln->effective_end->toDateString() >= $ymd;
                return $okDow && $okStart && $okEnd;
            })
            ->values();

        foreach ($lines as $line) {
            // Subシフトはスナップショット対象外
            $school = (string)($line->school_name ?? '');
            if (strcasecmp(trim($school), 'sub') === 0) {  // ← 完全一致（大文字小文字無視）
                continue;
            }
            // 二重生成の保険
            $exists = Event::query()
                ->whereDate('event_date', $ymd)
                ->where('source_schedule_line_id', $line->id)
                ->where('source_leave_id', $leave->id)
                ->exists();
            if ($exists) {
                continue;
            } // ブロック構文（波かっこあり）

            // event 作成
            $event = Event::create([
                'event_date'              => $ymd,
                'title'                   => 'null',
                'school_name'             => $line->school_name,
                'start_time'              => substr($line->start_time, 0, 8), // H:i[:s]対策で正規化
                'end_time'                => substr($line->end_time,   0, 8),
                'type'                    => 'regular_time',
                'assigned_user_id'        => null, // 後ほどSubシフトに担当してもらう
                'original_user_id'        => $userId,
                'Leave_type'              => (string) $leave->kind,
                'source_schedule_line_id' => $line->id,
                'source_leave_id'         => $leave->id,
                'status'                  => 'pending', // 後ほどSubシフトに担当してもらう  
                'notes'                   => sprintf('Snapshot for leave#%d (%s)', $leave->id, $leave->type),
            ]);

            // details コピー（lesson_start_time_id / lesson_id をスナップショットに保持）
            $lessonCodes = []; // ← lesson_code 収集用
            foreach ($line->details as $d) {
                // line 時間帯内の detail のみ（WorkProvider と同じ基準）
                $startHm = $d->start?->start_time?->format('H:i');
                if (!$startHm) continue;

                $lineStartHm = substr($line->start_time, 0, 5);
                $lineEndHm   = substr($line->end_time,   0, 5);

                $toMin = fn($hm) => ($hm && strlen($hm) >= 4) ? (intval(substr($hm, 0, 2)) * 60 + intval(substr($hm, 3, 2))) : null;
                $m = $toMin($startHm);
                $ls = $toMin($lineStartHm);
                $le = $toMin($lineEndHm);

                if ($m !== null && $ls !== null && $le !== null && $m >= $ls && $m <= $le) {
                    EventDetail::create([
                        'event_id'             => $event->id,
                        'schedule_detail_id'   => $d->id,
                        'lesson_start_time_id' => $d->lesson_start_time_id,
                        'lesson_id'            => $d->lesson_id,
                    ]);

                    // lesson_code を取得して配列に追加
                    if ($d->lesson_id) {
                        $code = Lesson::where('id', $d->lesson_id)->value('lesson_code');
                        if ($code) $lessonCodes[] = $code;
                    }
                }
            }

            // Lesson列に "AAA, BBB, CCC" のように保存
            if (!empty($lessonCodes)) {
                $event->Lesson = implode(', ', $lessonCodes);
                $event->save();
            }
        }
    }
}
