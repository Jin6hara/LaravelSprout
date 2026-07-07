<?php

/**
 * 休暇に紐づくカレンダースナップショット（イベント詳細）の生成・削除を管理するサービス。
 */
// app/Services/Calendar/LeaveSnapshotService.php
namespace App\Services\Calendar;

use App\Enums\EventStatus;
use App\Enums\LeaveStatus;
use App\Enums\ShiftType;
use App\Models\Event;
use App\Models\EventDetail;
use App\Models\Leave;
use App\Models\ScheduleLine;
use App\Services\Leave\GeneratedShiftDecisionService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LeaveSnapshotService
{
    public function __construct(private GeneratedShiftDecisionService $generatedShiftDecision) {}

    /**
     * Leaveに紐づくスナップショットを再生成
     */
    public function rebuildSnapshotsForLeave(Leave $leave): void
    {
        DB::transaction(function () use ($leave) {
            if (!in_array($leave->status, LeaveStatus::snapshotValues(), true)) {
                $this->generatedShiftDecision->applyAction(
                    $leave,
                    $leave->generatedShiftAction ?: GeneratedShiftDecisionService::ACTION_DETACH
                );
                return;
            }

            // 期間生成（end_date が null の場合は単日）
            $start = Carbon::parse($leave->start_date);
            $end   = $leave->end_date ? Carbon::parse($leave->end_date) : Carbon::parse($leave->start_date);

            // 不要になったスナップショットのみ削除（期間外・対象ユーザー変更）
            // → 期間内の既存スナップショットは残し、assigned_user 等の手動編集を保持する
            $this->deleteStaleSnapshotsForLeave($leave, $start, $end);

            // kind 変更を既存スナップショットへ反映
            $leave->generatedEvents()->update(['Leave_type' => (string) $leave->kind]);

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
        $this->generatedShiftDecision->applyAction($leave, GeneratedShiftDecisionService::ACTION_DELETE);
    }

    /**
     * 現在の Leave 内容と一致しなくなったスナップショットのみ削除
     * （期間外の日付、または対象ユーザーが変わったもの）
     */
    private function deleteStaleSnapshotsForLeave(Leave $leave, Carbon $start, Carbon $end): void
    {
        $leave->generatedEvents()
            ->where(function ($q) use ($leave, $start, $end) {
                $q->whereDate('event_date', '<', $start->toDateString())
                    ->orWhereDate('event_date', '>', $end->toDateString());

                if ($leave->user_id === null) {
                    $q->orWhereNotNull('original_user_id');
                } else {
                    $q->orWhere(fn($qq) => $qq
                        ->where('original_user_id', '!=', $leave->user_id)
                        ->orWhereNull('original_user_id'));
                }
            })
            ->delete();
    }

    /**
     * Leave が Rejected / Cancelled などになった場合、紐づく Event は削除せず手動シフトとして残す
     */
    public function detachSnapshotsForLeave(Leave $leave): void
    {
        $this->generatedShiftDecision->applyAction($leave, GeneratedShiftDecisionService::ACTION_DETACH);
    }

    /**
     * 特定日のスナップショット生成
     */
    private function snapshotDay(Leave $leave, Carbon $date): void
    {
        $userId = $leave->user_id;
        $dow    = $date->dayOfWeek; // 0..6
        $ymd    = $date->toDateString();
        $skipSubs = true; // ← OFFにしたいときは false に

        // ▼ schedule_lines.user_id で直接取得
        $lines = ScheduleLine::with(['details', 'details.lesson'])
            ->where('user_id', $userId)
            ->whereDate('effective_start', '<=', $ymd)
            ->whereDate('effective_end', '>=', $ymd)
            ->where('dow', $dow)
            ->get()
            // Subシフトはスナップショット対象外
            ->reject(fn($line) => strcasecmp(trim((string)($line->school_name ?? '')), 'sub') === 0)
            ->values();

        $dayEvents = fn() => Event::query()
            ->whereDate('event_date', $ymd)
            ->where('source_leave_id', $leave->id);

        if ($lines->isEmpty()) {
            $dayEvents()->delete();
            return; // 割当なし → スナップショット対象なし
        }

        if ($skipSubs) {
            $existsInSubs = DB::table('subs')
                ->where('user_id', $userId)
                ->whereDate('sub_date', $ymd)
                ->exists();

            if ($existsInSubs) {
                $dayEvents()->delete();
                return;
            }
        }

        // スケジュール変更等で対象外になったラインのスナップショットを削除
        $dayEvents()
            ->whereNotIn('source_schedule_line_id', $lines->pluck('id')->all())
            ->delete();

        $existingLineIds = $dayEvents()
            ->whereIn('source_schedule_line_id', $lines->pluck('id')->all())
            ->pluck('source_schedule_line_id')
            ->flip();

        foreach ($lines as $line) {
            if ($existingLineIds->has($line->id)) {
                continue;
            }

            // event 作成
            $originalUser  = \App\Models\User::find($userId);
            $districtId    = $originalUser?->district_id ?? $leave->district_id;
            $departmentId  = $originalUser?->department_id ?? $leave->department_id;

            $event = Event::create([
                'event_date'              => $ymd,
                'title'                   => 'Cover Shift',
                'school_name'             => $line->school_name,
                'start_time'              => substr($line->start_time, 0, 8),
                'end_time'                => substr($line->end_time,   0, 8),
                'type'                    => ShiftType::RegularTime->value,
                'assigned_user_id'        => null,
                'original_user_id'        => $userId,
                'Leave_type'              => (string) $leave->kind,
                'source_schedule_line_id' => $line->id,
                'source_leave_id'         => $leave->id,
                'status'                  => EventStatus::Pending->value,
                'notes'                   => null,
                'district_id'             => $districtId,
                'department_id'           => $departmentId,
            ]);

            // details コピー（start_time / lesson_id をスナップショットに保持）
            $lessonCodes = [];
            $seen = [];

            foreach ($line->details as $d) {
                $okStart = $d->effective_start && $d->effective_start->toDateString() <= $ymd;
                $okEnd   = is_null($d->effective_end) || $d->effective_end->toDateString() >= $ymd;
                if (!($okStart && $okEnd)) continue;

                $startHm = $d->start_time ? substr($d->start_time, 0, 5) : null;
                if (!$startHm) continue;

                $lineStartHm = substr($line->start_time, 0, 5);
                $lineEndHm   = substr($line->end_time,   0, 5);

                $toMin = fn($hm) => ($hm && strlen($hm) >= 4) ? (intval(substr($hm, 0, 2)) * 60 + intval(substr($hm, 3, 2))) : null;
                $m = $toMin($startHm);
                $ls = $toMin($lineStartHm);
                $le = $toMin($lineEndHm);

                if ($m !== null && $ls !== null && $le !== null && $m >= $ls && $m <= $le) {
                    $k = $d->start_time . '-' . $d->lesson_id;
                    if (isset($seen[$k])) {
                        continue;
                    }
                    $seen[$k] = true;

                    EventDetail::create([
                        'event_id'           => $event->id,
                        'schedule_detail_id' => $d->id,
                        'start_time'         => $d->start_time,
                        'lesson_id'          => $d->lesson_id,
                    ]);

                    $code = $d->lesson?->lesson_code;
                    if ($code) {
                        $lessonCodes[] = $code;
                    }
                }
            }

            if (!empty($lessonCodes)) {
                $event->Lesson = implode(', ', $lessonCodes);
                $event->save();
            }
        }
    }
}
