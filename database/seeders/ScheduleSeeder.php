<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Schedule;
use App\Models\ScheduleLine;
use App\Models\UserScheduleAssignment;

// database/seeders/ScheduleSeeder.php
class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        // 共通: 期間
        $s = '2025-04-01';
        $e = '2026-03-31';

        // ---------- schedule 1 (James, 7:45, 週5日・土2ライン) ----------
        $sc1 = Schedule::updateOrCreate(
            ['label' => 'James weekly', 'effective_start' => $s],
            ['effective_end' => $e, 'total_minutes' => 465, 'is_active' => true]
        );
        $this->lines($sc1, [
            [3, 'Ikoma', '14:00', '21:45'],      // 水
            [4, 'Tennoji MP', '14:00', '21:45'], // 木
            [5, 'Umada GB', '13:30', '21:15'],   // 金
            [6, 'Yao', '10:00', '14:00'],        // 土
            [6, 'Fujitera', '15:00', '17:45'],   // 土(2本目)
            [0, 'Ikoma', '10:00', '17:45'],      // 日
        ], $s, $e);

        // ---------- schedule 2 (Jason, 7:45, 週5日・1日1ライン) ----------
        $sc2 = Schedule::updateOrCreate(
            ['label' => 'Jason weekly', 'effective_start' => $s],
            ['effective_end' => $e, 'total_minutes' => 465, 'is_active' => true]
        );
        $this->lines($sc2, [
            [1, 'Sub', '14:00', '21:45'],  // 月
            [2, 'Sub', '14:00', '21:45'],  // 火
            [3, 'Umada GB', '13:30', '21:15'], // 水
            [6, 'Yao', '10:00', '17:45'],  // 土
            [0, 'Sub', '10:00', '17:45'],  // 日
        ], $s, $e);

        // ---------- schedule 3 (Jake, スケジュール変更パターン) ----------
        $sc3 = Schedule::updateOrCreate(
            ['label' => 'Jake change', 'effective_start' => $s],
            ['effective_end' => $e, 'total_minutes' => 465, 'is_active' => true]
        );
        // 4/1〜7/31: 土のみ
        $this->lines($sc3, [
            [6, 'Sannomiya', '10:00', '17:45'],
        ], '2025-04-01', '2025-07-31');
        // 8/1〜3/31: 金のみ
        $this->lines($sc3, [
            [5, 'Umada GB', '14:00', '21:45'],
        ], '2025-08-01', '2026-03-31');

        // 割当
        UserScheduleAssignment::updateOrCreate(
            ['user_id' => 4, 'schedule_id' => $sc1->id, 'start_date' => $s],
            ['end_date' => $e]
        );
        UserScheduleAssignment::updateOrCreate(
            ['user_id' => 7, 'schedule_id' => $sc2->id, 'start_date' => $s],
            ['end_date' => $e]
        );
        UserScheduleAssignment::updateOrCreate(
            ['user_id' => 5, 'schedule_id' => $sc3->id, 'start_date' => $s],
            ['end_date' => $e]
        );
    }

    private function lines(Schedule $sc, array $rows, string $start, string $end): void
    {
        foreach ($rows as [$dow, $school, $from, $to]) {
            ScheduleLine::updateOrCreate(
                ['schedule_id' => $sc->id, 'dow' => $dow, 'start_time' => $from, 'effective_start' => $start],
                ['school_name' => $school, 'end_time' => $to, 'effective_end' => $end]
            );
        }
    }
}
