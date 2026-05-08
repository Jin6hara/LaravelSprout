<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleDetailsCopySeeder extends Seeder
{
    public function run(): void
    {
        $effectiveStart = '2026-04-01';
        $effectiveEnd   = '2027-03-31';

        // lesson_id => start_time（旧テーブルIDを時刻値に変換済み）
        $rows = [
            1 => '14:00:00',
            2 => '15:25:00',
            3 => '16:50:00',
            4 => '18:15:00',
            5 => '19:40:00',
            6 => '21:05:00',
        ];

        $targets = [33, 38];

        foreach ($targets as $lineId) {
            foreach ($rows as $lessonId => $startTime) {
                $exists = DB::table('schedule_details')->where([
                    'schedule_line_id' => $lineId,
                    'start_time'       => $startTime,
                    'lesson_id'        => $lessonId,
                    'effective_start'  => $effectiveStart,
                ])->exists();

                if ($exists) continue;

                DB::table('schedule_details')->insert([
                    'schedule_line_id' => $lineId,
                    'start_time'       => $startTime,
                    'lesson_id'        => $lessonId,
                    'effective_start'  => $effectiveStart,
                    'effective_end'    => $effectiveEnd,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
            }
        }
    }
}
