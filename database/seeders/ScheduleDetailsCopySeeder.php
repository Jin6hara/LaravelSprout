<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleDetailsCopySeeder extends Seeder
{
    public function run(): void
    {
        // 画像と同じ内容
        $effectiveStart = '2025-04-01';
        $effectiveEnd   = '2026-03-31';

        // lesson_id => lesson_start_time_id の対応
        $rows = [
            1 => 61,
            2 => 78,
            3 => 95,
            4 => 112,
            5 => 129,
            6 => 146,
        ];

        // 作成対象の schedule_line_id
        $targets = [33, 38];

        foreach ($targets as $lineId) {
            foreach ($rows as $lessonId => $lstId) {
                // 既存があればスキップ（重複作成防止）
                $exists = DB::table('schedule_details')->where([
                    'schedule_line_id'      => $lineId,
                    'lesson_start_time_id'  => $lstId,
                    'lesson_id'             => $lessonId,
                    'effective_start'       => $effectiveStart,
                ])->exists();

                if ($exists) continue;

                DB::table('schedule_details')->insert([
                    'schedule_line_id'      => $lineId,
                    'lesson_start_time_id'  => $lstId,
                    'lesson_id'             => $lessonId,
                    'effective_start'       => $effectiveStart,
                    'effective_end'         => $effectiveEnd,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }
        }
    }
}
