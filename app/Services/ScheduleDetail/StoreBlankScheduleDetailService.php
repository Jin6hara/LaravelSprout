<?php

namespace App\Services\ScheduleDetail;

use App\Models\Lesson;
use App\Models\ScheduleDetail;
use App\Models\ScheduleLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StoreBlankScheduleDetailService
{
    public function handle(ScheduleLine $line): ScheduleDetail
    {
        return DB::transaction(function () use ($line) {
            $lesson = Lesson::query()->orderBy('id')->first()
                ?? Lesson::create([
                    'lesson_name'   => '未設定',
                    'lesson_code'   => 'TEMP',
                    'note'          => null,
                    'lesson_minute' => 0,
                    'lesson_type'   => null,
                ]);

            return ScheduleDetail::create([
                'schedule_line_id' => $line->id,
                'start_time'       => '00:00:00',
                'lesson_id'        => $lesson->id,
                'effective_start'  => Carbon::today()->toDateString(),
                'effective_end'    => null,
            ]);
        });
    }
}
