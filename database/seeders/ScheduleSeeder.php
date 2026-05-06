<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Schedule;
use App\Models\ScheduleLine;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        // 共通: 期間
        $s = '2026-04-01';
        $e = '2027-03-31';

        // ---------- schedule 1 (James, 7:45, 週5日・土2ライン) ----------
        $sc1 = Schedule::updateOrCreate(
            [
                'user_id'        => 4,
                'label'          => 'TS',
                'effective_start' => $s,
            ],
            [
                'effective_end'  => $e,
                'total_minutes'  => 465,
                'is_active'      => true,
            ]
        );
        $this->lines($sc1, [
            [3, 'Ikoma',      '14:00', '21:45'], // 水
            [4, 'Tennoji MP', '14:00', '21:45'], // 木
            [5, 'Umada GB',   '13:30', '21:15'], // 金
            [6, 'Yao',        '10:00', '14:00'], // 土
            [6, 'Fujitera',   '15:00', '17:45'], // 土(2本目)
            [0, 'Ikoma',      '10:00', '17:45'], // 日
        ], $s, $e);

        // ---------- schedule 2 (Jason, 7:45, 週5日・1日1ライン) ----------
        $sc2 = Schedule::updateOrCreate(
            [
                'user_id'        => 7,
                'label'          => 'TS',
                'effective_start' => $s,
            ],
            [
                'effective_end'  => $e,
                'total_minutes'  => 465,
                'is_active'      => true,
            ]
        );
        $this->lines($sc2, [
            [1, 'Sub',      '14:00', '21:45'],   // 月
            [2, 'Sub',      '14:00', '21:45'],   // 火
            [3, 'Umada GB', '13:30', '21:15'],   // 水
            [6, 'Yao',      '10:00', '17:45'],   // 土
            [0, 'Sub',      '10:00', '17:45'],   // 日
        ], $s, $e);

        // ---------- schedule 3 (Jake, スケジュール変更パターン) ----------
        $sc3 = Schedule::updateOrCreate(
            [
                'user_id'        => 5,
                'label'          => 'HS',
                'effective_start' => $s,
            ],
            [
                'effective_end'  => $e,
                'total_minutes'  => 465,
                'is_active'      => true,
            ]
        );
        // 4/1〜7/31: 土のみ
        $this->lines($sc3, [
            [6, 'Sannomiya', '10:00', '17:45'],
        ], '2026-04-01', '2026-07-31');

        // 8/1〜3/31: 金のみ
        $this->lines($sc3, [
            [5, 'Umada GB',  '14:00', '21:45'],
        ], '2026-08-01', '2027-03-31');

        // ★ 旧: UserScheduleAssignment は不要なので削除
        // UserScheduleAssignment::updateOrCreate(...) も全削除でOK

        // ---------- schedule 4 (user_id=1, Mon/Tue off, Ikoma 5日: Wed–Sun) ----------
        $sc4 = Schedule::updateOrCreate(
            ['user_id' => 1, 'label' => 'TS', 'effective_start' => $s],
            ['effective_end' => $e, 'total_minutes' => 465, 'is_active' => true]
        );
        $this->lines($sc4, [
            [3, 'Ikoma', '14:00', '21:45'], // Wed
            [4, 'Ikoma', '14:00', '21:45'], // Thu
            [5, 'Ikoma', '14:00', '21:45'], // Fri
            [6, 'Ikoma', '10:00', '17:45'], // Sat
            [0, 'Ikoma', '10:00', '17:45'], // Sun
        ], $s, $e);

        // ---------- schedule 5 (user_id=2, Mon/Tue off, IUmeda GB 5日: Wed–Sun) ----------
        $sc5 = Schedule::updateOrCreate(
            ['user_id' => 2, 'label' => 'TS', 'effective_start' => $s],
            ['effective_end' => $e, 'total_minutes' => 465, 'is_active' => true]
        );
        $this->lines($sc5, [
            [3, 'Umeda GB', '14:00', '21:45'], // Wed
            [4, 'Umeda GB', '14:00', '21:45'], // Thu
            [5, 'Umeda GB', '14:00', '21:45'], // Fri
            [6, 'Umeda GB', '10:00', '17:45'], // Sat
            [0, 'Umeda GB', '10:00', '17:45'], // Sun
        ], $s, $e);

        // ---------- schedule 6 (user_id=3, Mon/Tue off, Sub: Wed–Sat / Hyper: Sun) ----------
        $sc6 = Schedule::updateOrCreate(
            ['user_id' => 3, 'label' => 'TS', 'effective_start' => $s],
            ['effective_end' => $e, 'total_minutes' => 465, 'is_active' => true]
        );
        $this->lines($sc6, [
            [3, 'Sub',   '14:00', '21:45'], // Wed
            [4, 'Sub',   '14:00', '21:45'], // Thu
            [5, 'Sub',   '14:00', '21:45'], // Fri
            [6, 'Sub',   '10:00', '17:45'], // Sat
            [0, 'Hyper', '10:00', '17:45'], // Sun
        ], $s, $e);

        // ---------- schedule 7 (user_id=8, Thu/Fri off, ITennoji MP 5日: Sat–Wed) ----------
        $sc7 = Schedule::updateOrCreate(
            ['user_id' => 8, 'label' => 'TS', 'effective_start' => $s],
            ['effective_end' => $e, 'total_minutes' => 465, 'is_active' => true]
        );
        $this->lines($sc7, [
            [0, 'Tennoji MP', '10:00', '17:45'], // Sun
        ],'2026-04-01', '2026-11-30');

        $this->lines($sc7, [
            [0, 'Kids Tennoji', '10:00', '17:45'], // Sun
        ],'2026-12-01', '2027-03-31');

        $this->lines($sc7, [
            [6, 'Tennoji MP', '10:00', '17:45'], // Sat
            [1, 'Tennoji MP', '14:00', '21:45'], // Mon
            [2, 'Tennoji MP', '14:00', '21:45'], // Tue
            [3, 'Tennoji MP', '14:00', '21:45'], // Wed
        ], '2026-04-01', '2027-03-31');

        // ---------- schedule 8 (user_id=9, Thu/Fri off, Yao: Sun–Wed / Sub: Sat) ----------
        $sc8 = Schedule::updateOrCreate(
            ['user_id' => 9, 'label' => 'TS', 'effective_start' => $s],
            ['effective_end' => $e, 'total_minutes' => 465, 'is_active' => true]
        );
        $this->lines($sc8, [
            [6, 'Sub', '10:00', '17:45'], // Sat
            [0, 'Yao', '10:00', '17:45'], // Sun
            [1, 'Yao', '14:00', '21:45'], // Mon
            [2, 'Yao', '14:00', '21:45'], // Tue
            [3, 'Yao', '14:00', '21:45'], // Wed
        ], $s, $e);

        // ---------- schedule 9 (user_id=10, Thu/Fri off, Fuse: Sun–Wed / Sub→Fuse: Sat) ----------
        $sc9 = Schedule::updateOrCreate(
            ['user_id' => 10, 'label' => 'TS', 'effective_start' => $s],
            ['effective_end' => $e, 'total_minutes' => 465, 'is_active' => true]
        );

        // 2026-04-01〜2026-11-30: 土=Sub
        $this->lines($sc9, [
            [0, 'Fuse', '10:00', '17:45'], // Sun
            [1, 'Fuse', '14:00', '21:45'], // Mon
            [2, 'Fuse', '14:00', '21:45'], // Tue
            [3, 'Fuse', '14:00', '21:45'], // Wed
        ], '2026-04-01', '2027-03-31');

        // 2026-04-01〜2026-11-30: 土=Sub
        $this->lines($sc9, [
            [6, 'Sub',  '10:00', '17:45'], // Sat
        ], '2026-04-01', '2026-12-31');

        // 2026-12-01〜2027-03-31: 土=Fuse（Sub→Fuseに変更）
        $this->lines($sc9, [
            [6, 'Fuse', '10:00', '17:45'], // Sat
        ], '2026-01-01', '2027-03-31');
    }

    private function lines(Schedule $sc, array $rows, string $start, string $end): void
    {
        foreach ($rows as [$dow, $school, $from, $to]) {
            ScheduleLine::updateOrCreate(
                [
                    'schedule_id'    => $sc->id,
                    'dow'            => $dow,
                    'start_time'     => $from,
                    'effective_start' => $start,
                ],
                [
                    'school_name'    => $school,
                    'end_time'       => $to,
                    'effective_end'  => $end,
                ]
            );
        }
    }
}
