<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Enums\ExpenseReportStatus;
use Carbon\Carbon;

class ExpenseReportSeptemberSeeder extends Seeder
{
    public function run(): void
    {
        $tz    = 'Asia/Tokyo';
        $now   = Carbon::now($tz);
        $year  = (int) $now->format('Y');
        $month = 10; // 10月
        // 1=>A, 2=>B, ... 19=>S
        $y = array_combine(range(1, 19), range('A', 'S'));


        for ($i = 1; $i <= 10; $i++) {
            $user = User::find($i);
            if (!$user) {
                continue; // 念のため存在チェック
            }

            $employeeCode = $user->employee_code ?? str_pad($i, 6, '0', STR_PAD_LEFT);

            // user_id + year + month の一意制約を守りつつ、再実行にも強い
            DB::table('expense_reports')->updateOrInsert(
                [
                    'user_id' => $user->id,
                    'year'    => $year,
                    'month'   => $month,
                ],
                [
                    'employee_code'             => $employeeCode,
                    'employee_family_name'      => "{$i}hara",
                    'employee_first_name'       => "{$y[$i]}im",
                    'status'                    => ExpenseReportStatus::DRAFT->value,
                    'submitted_at'              => null,
                    'approved_at'               => null,
                    'paid_at'                   => null,
                    'total_amount'              => 0,
                    'created_at'                => $now,
                    'updated_at'                => $now,
                ]
            );
        }
    }
}
