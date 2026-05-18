<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ScheduleLine;

class ScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $kinki  = \App\Models\District::where('name', '西日本(阪)')->first();
        $native = \App\Models\Department::where('name', 'Native')->first();

        $s = '2026-04-01';
        $e = '2027-03-31';

        // user_id=4 (James)
        $this->lines(4, [
            [3, 'Ikoma',      '14:00', '21:45'],
            [4, 'Tennoji MP', '14:00', '21:45'],
            [5, 'Umada GB',   '13:30', '21:15'],
            [6, 'Yao',        '10:00', '14:00'],
            [6, 'Fujitera',   '15:00', '17:45'],
            [0, 'Ikoma',      '10:00', '17:45'],
        ], $s, $e, $kinki, $native);

        // user_id=7 (Jason)
        $this->lines(7, [
            [1, 'Sub',      '14:00', '21:45'],
            [2, 'Sub',      '14:00', '21:45'],
            [3, 'Umada GB', '13:30', '21:15'],
            [6, 'Yao',      '10:00', '17:45'],
            [0, 'Sub',      '10:00', '17:45'],
        ], $s, $e, $kinki, $native);

        // user_id=5 (Jake): 4/1〜7/31
        $this->lines(5, [
            [6, 'Sannomiya', '10:00', '17:45'],
        ], '2026-04-01', '2026-07-31', $kinki, $native);

        // user_id=5 (Jake): 8/1〜3/31
        $this->lines(5, [
            [5, 'Umada GB', '14:00', '21:45'],
        ], '2026-08-01', '2027-03-31', $kinki, $native);

        // user_id=1
        $this->lines(1, [
            [3, 'Ikoma', '14:00', '21:45'],
            [4, 'Ikoma', '14:00', '21:45'],
            [5, 'Ikoma', '14:00', '21:45'],
            [6, 'Ikoma', '10:00', '17:45'],
            [0, 'Ikoma', '10:00', '17:45'],
        ], $s, $e, $kinki, $native);

        // user_id=2
        $this->lines(2, [
            [3, 'Umeda GB', '14:00', '21:45'],
            [4, 'Umeda GB', '14:00', '21:45'],
            [5, 'Umeda GB', '14:00', '21:45'],
            [6, 'Umeda GB', '10:00', '17:45'],
            [0, 'Umeda GB', '10:00', '17:45'],
        ], $s, $e, $kinki, $native);

        // user_id=3
        $this->lines(3, [
            [3, 'Sub',   '14:00', '21:45'],
            [4, 'Sub',   '14:00', '21:45'],
            [5, 'Sub',   '14:00', '21:45'],
            [6, 'Sub',   '10:00', '17:45'],
            [0, 'Hyper', '10:00', '17:45'],
        ], $s, $e, $kinki, $native);

        // user_id=8: Sun 4/1〜11/30
        $this->lines(8, [
            [0, 'Tennoji MP', '10:00', '17:45'],
        ], '2026-04-01', '2026-11-30', $kinki, $native);

        // user_id=8: Sun 12/1〜3/31
        $this->lines(8, [
            [0, 'Kids Tennoji', '10:00', '17:45'],
        ], '2026-12-01', '2027-03-31', $kinki, $native);

        // user_id=8: Sat/Mon/Tue/Wed 通年
        $this->lines(8, [
            [6, 'Tennoji MP', '10:00', '17:45'],
            [1, 'Tennoji MP', '14:00', '21:45'],
            [2, 'Tennoji MP', '14:00', '21:45'],
            [3, 'Tennoji MP', '14:00', '21:45'],
        ], $s, $e, $kinki, $native);

        // user_id=9
        $this->lines(9, [
            [6, 'Sub', '10:00', '17:45'],
            [0, 'Yao', '10:00', '17:45'],
            [1, 'Yao', '14:00', '21:45'],
            [2, 'Yao', '14:00', '21:45'],
            [3, 'Yao', '14:00', '21:45'],
        ], $s, $e, $kinki, $native);

        // user_id=10: Sun〜Wed 通年
        $this->lines(10, [
            [0, 'Fuse', '10:00', '17:45'],
            [1, 'Fuse', '14:00', '21:45'],
            [2, 'Fuse', '14:00', '21:45'],
            [3, 'Fuse', '14:00', '21:45'],
        ], '2026-04-01', '2027-03-31', $kinki, $native);

        // user_id=10: Sat=Sub 4/1〜12/31
        $this->lines(10, [
            [6, 'Sub', '10:00', '17:45'],
        ], '2026-04-01', '2026-12-31', $kinki, $native);

        // user_id=10: Sat=Fuse 1/1〜3/31
        $this->lines(10, [
            [6, 'Fuse', '10:00', '17:45'],
        ], '2027-01-01', '2027-03-31', $kinki, $native);
    }

    private function lines(int $userId, array $rows, string $start, string $end, $kinki, $native): void
    {
        foreach ($rows as [$dow, $school, $from, $to]) {
            ScheduleLine::updateOrCreate(
                [
                    'user_id'         => $userId,
                    'dow'             => $dow,
                    'start_time'      => $from,
                    'effective_start' => $start,
                ],
                [
                    'school_name'   => $school,
                    'end_time'      => $to,
                    'effective_end' => $end,
                    'total_minutes' => 465,
                    'district_id'   => $kinki->id,
                    'department_id' => $native->id,
                ]
            );
        }
    }
}
