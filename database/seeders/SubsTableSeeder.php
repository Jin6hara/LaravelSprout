<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubsTableSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('subs')->insert([
            [
                'user_id'        => 2,
                'sub_date'       => '2026-11-06',
                'start_time'     => '14:00',
                'end_time'       => '21:45',
                'note'           => 'Adjustment Day',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'user_id'        => 2,
                'sub_date'       => '2026-11-07',
                'start_time'     => '14:00',
                'end_time'       => '21:45',
                'note'           => 'Adjustment Day',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'user_id'        => 2,
                'sub_date'       => '2026-11-08',
                'start_time'     => '10:00',
                'end_time'       => '17:45',
                'note'           => 'Adjustment Day',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
            [
                'user_id'        => 1,
                'sub_date'       => '2026-11-08',
                'start_time'     => '10:00',
                'end_time'       => '17:45',
                'note'           => 'Adjustment Day',
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ]);
    }
}
