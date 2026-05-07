<?php

namespace App\Services\ScheduleCsv;

use App\Models\{ScheduleLine, ScheduleDetail, Lesson, LessonStartTime};
use Carbon\Carbon;
use App\Support\SchoolName;
use Illuminate\Support\Facades\DB;

class ScheduleLineCsvImportService
{
    public function import(string $csvPath, ?int $scheduleId = null): array
    {
        $fp = fopen($csvPath, 'r');
        $header = fgetcsv($fp);

        $count = 0;

        DB::transaction(function () use ($fp, $header, $scheduleId, &$count) {
            while ($row = fgetcsv($fp)) {
                $r = array_combine($header, $row);

                $dow = $this->toDowIndex($r['dow']);
                $school = SchoolName::normalize($r['school_name']);
                $start = $this->toTime($r['start_time']);
                $end   = $this->toTime($r['end_time']);
                $winStart = $r['effective_start'];
                $winEnd   = $r['effective_end'] ?: null;

                $lessonStart = $this->toTime($r['lesson_start_time']);
                $lsTimeId = LessonStartTime::where('start_time', $lessonStart)->value('id');

                $lessonId = Lesson::where('lesson_code', $r['lesson_code'])->value('id');

                if (!$lsTimeId || !$lessonId) continue;

                $line = ScheduleLine::query()
                    ->when(
                        is_null($scheduleId),
                        fn($q) => $q->whereNull('schedule_id'),
                        fn($q) => $q->where('schedule_id', $scheduleId)
                    )
                    ->where('dow', $dow)
                    ->whereEqualsInsensitive('school_name', $school)
                    ->where('start_time', $start)
                    ->where('end_time', $end)
                    ->where('effective_start', $winStart)
                    ->where('effective_end', $winEnd)
                    ->first();

                if (!$line) {
                    $line = ScheduleLine::create([
                        'schedule_id'     => $scheduleId,
                        'dow'             => $dow,
                        'school_name'     => $school,
                        'start_time'      => $start,
                        'end_time'        => $end,
                        'effective_start' => $winStart,
                        'effective_end'   => $winEnd,
                    ]);
                }

                ScheduleDetail::updateOrCreate([
                    'schedule_line_id'     => $line->id,
                    'lesson_start_time_id' => $lsTimeId,
                    'lesson_id'            => $lessonId,
                    'effective_start'      => $winStart,
                    'effective_end'        => $winEnd,
                ]);

                $count++;
            }
        });

        return ['count' => $count];
    }

    private function toDowIndex($d): int
    {
        return ['日' => 0, '月' => 1, '火' => 2, '水' => 3, '木' => 4, '金' => 5, '土' => 6][mb_substr($d, 0, 1)] ?? 0;
    }

    private function toTime($v): string
    {
        $v = preg_replace('/\D/', '', $v);
        if (strlen($v) === 3) $v = '0' . $v;
        return substr($v, 0, 2) . ':' . substr($v, 2, 2) . ':00';
    }
}
