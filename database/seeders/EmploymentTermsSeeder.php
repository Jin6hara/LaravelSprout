<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class EmploymentTermsSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 19; $i++) {
            $user = User::where('employee_code', str_pad($i, 5, '0', STR_PAD_LEFT))->first();
            if (!$user) continue;

            if ($i >= 1 && $i <= 13) {
                // 在籍：2020/01/01〜（無期限）
                $start = '2020-01-01';
                $end   = null;
            } elseif ($i >= 14 && $i <= 16) {
                // 退職：2020/01/01〜2024/12/31
                $start = '2020-01-01';
                $end   = '2024-12-31';
            } else { // 17〜19
                // 入社待ち：2026/01/01〜
                $start = '2026-01-01';
                $end   = null;
            }

            $user->employmentTerms()->create([
                'start_date' => $start,
                'end_date'   => $end,
                'note'       => null,
            ]);
        }
    }
}
