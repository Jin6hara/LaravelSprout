<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class LeavePeriodsSeeder extends Seeder
{
    public function run(): void
    {
        // 11〜13 を 2026/01/01〜2026/12/31 で休職
        for ($i = 11; $i <= 13; $i++) {
            $user = User::where('employee_code', str_pad($i, 5, '0', STR_PAD_LEFT))->first();
            if (!$user) continue;

            $term = $user->employmentTerms()->orderBy('start_date', 'desc')->first();
            if (!$term) continue;

            $term->leavePeriods()->create([
                'start_date' => '2026-01-01',
                'end_date'   => '2026-12-31',
                'reason'     => '休職（テストデータ）',
            ]);
        }
    }
}
