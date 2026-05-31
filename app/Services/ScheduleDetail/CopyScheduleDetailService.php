<?php

namespace App\Services\ScheduleDetail;

use App\Models\ScheduleDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CopyScheduleDetailService
{
    public function handle(ScheduleDetail $detail): ScheduleDetail
    {
        return DB::transaction(function () use ($detail) {
            $created = $detail->replicate(['id', 'created_at', 'updated_at']);

            $baseStart = Carbon::parse($detail->effective_start);
            $baseEnd   = $detail->effective_end ? Carbon::parse($detail->effective_end) : null;

            $delta = 0;
            do {
                $candidateStart = $baseStart->copy()->addDays($delta)->toDateString();
                $exists = ScheduleDetail::query()
                    ->where('schedule_line_id', $detail->schedule_line_id)
                    ->where('start_time',       $detail->start_time)
                    ->where('lesson_id',        $detail->lesson_id)
                    ->whereDate('effective_start', $candidateStart)
                    ->exists();

                if (!$exists) {
                    $created->effective_start = $candidateStart;
                    $created->effective_end = $baseEnd ? $baseEnd->copy()->addDays($delta)->toDateString() : null;
                    break;
                }
                $delta++;
            } while ($delta < 3650);

            if (!isset($created->effective_start)) {
                throw new \RuntimeException('複写先の日付を確保できませんでした。');
            }

            $created->save();

            return $created;
        });
    }
}
