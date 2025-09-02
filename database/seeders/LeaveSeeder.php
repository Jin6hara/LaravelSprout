<?php

namespace Database\Seeders;

use App\Models\Leave;
use Illuminate\Database\Seeder;

class LeaveSeeder extends Seeder
{
    public function run(): void
    {
        // user_id=7
        Leave::create([
            'user_id'     => 7,
            'start_date'  => '2025-10-14',
            'kind'        => 'absence',
            'excused'     => 'unexcused',   // 欠席は基本unexcused
            'reason'      => '欠勤（連絡なし）',
            'status'      => 'approved',
        ]);
        Leave::create([
            'user_id'     => 7,
            'start_date'  => '2025-10-15',
            'kind'        => 'paid',        // 有給
            'excused'     => 'excused',
            'reason'      => null,
            'status'      => 'approved',
        ]);
        Leave::create([
            'user_id'     => 7,
            'start_date'  => '2025-10-16',
            'kind'        => 'paid',
            'excused'     => 'excused',
            'reason'      => null,
            'status'      => 'approved',
        ]);

        // user_id=4 （結婚式：5日連続）
        foreach (['2025-10-15','2025-10-16','2025-10-17','2025-10-18','2025-10-19'] as $d) {
            Leave::create([
                'user_id'     => 4,
                'start_date'  => $d,
                'kind'        => 'special',
                'special_type'=> '結婚式',
                'excused'     => 'excused',
                'reason'      => null,
                'status'      => 'approved',
            ]);
        }
    }
}
