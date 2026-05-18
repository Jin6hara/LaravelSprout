<?php

namespace Database\Seeders;

use App\Models\Leave;
use Illuminate\Database\Seeder;

class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        $kinki  = \App\Models\District::where('name', '西日本(阪)')->first();
        $native = \App\Models\Department::where('name', 'Native')->first();

        // user_id=7 （Absence）
        Leave::create([
            'user_id'       => 7,
            'start_date'    => '2026-10-14',
            'kind'          => 'absence',
            'excused'       => 'unexcused',   // 欠席は基本unexcused
            'reason'        => 'Sick',
            'status'        => 'pending',
            'district_id'   => $kinki->id,
            'department_id' => $native->id,
        ]);

        // user_id=7 （Absence）
        Leave::create([
            'user_id'       => 7,
            'start_date'    => '2026-10-31',
            'kind'          => 'absence',
            'excused'       => 'unexcused',   // 欠席は基本unexcused
            'reason'        => 'Sick',
            'status'        => 'pending',
            'district_id'   => $kinki->id,
            'department_id' => $native->id,
        ]);
        // user_id=7 （ALP）
        Leave::create([
            'user_id'       => 7,
            'start_date'    => '2026-10-15',
            'end_date'      => '2026-10-16',
            'kind'          => 'paid',        // ALPはpaid
            'excused'       => 'excused',
            'reason'        => null,
            'status'        => 'pending',
            'district_id'   => $kinki->id,
            'department_id' => $native->id,
        ]);

        // user_id=4 （結婚式：5日連続）
        Leave::create([
            'user_id'       => 4,
            'start_date'    => '2026-11-12',
            'end_date'      => '2026-11-16',
            'kind'          => 'special',
            'special_type'  => 'Wedding',
            'excused'       => 'excused',
            'reason'        => null,
            'status'        => 'approved',
            'district_id'   => $kinki->id,
            'department_id' => $native->id,
        ]);

        //  user_id=4 （Absence）
        Leave::create([
            'user_id'       => 4,
            'start_date'    => '2026-10-31',
            'kind'          => 'absence',
            'special_type'  => null,
            'excused'       => 'unexcused',
            'reason'        => null,
            'status'        => 'pending',
            'district_id'   => $kinki->id,
            'department_id' => $native->id,
        ]);

        //  user_id=4 （Absence）
        Leave::create([
            'user_id'       => 4,
            'start_date'    => '2026-11-01',
            'kind'          => 'absence',
            'special_type'  => null,
            'excused'       => 'unexcused',
            'reason'        => null,
            'status'        => 'pending',
            'district_id'   => $kinki->id,
            'department_id' => $native->id,
        ]);

        // user_id=8 （Absence）
        Leave::create([
            'user_id'       => 8,
            'start_date'    => '2026-11-04',
            'kind'          => 'absence',
            'special_type'  => null,
            'excused'       => 'unexcused',
            'reason'        => null,
            'status'        => 'pending',
            'district_id'   => $kinki->id,
            'department_id' => $native->id,
        ]);

        // user_id=9 （Absence）
        Leave::create([
            'user_id'       => 9,
            'start_date'    => '2026-11-04',
            'kind'          => 'absence',
            'special_type'  => null,
            'excused'       => 'unexcused',
            'reason'        => null,
            'status'        => 'pending',
            'district_id'   => $kinki->id,
            'department_id' => $native->id,
        ]);
    }
}
