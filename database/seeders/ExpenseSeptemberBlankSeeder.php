<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Enums\ExpenseTripType;
use App\Enums\ExpenseCategory;
use Carbon\Carbon;

class ExpenseSeptemberBlankSeeder extends Seeder
{
    public function run(): void
    {
        $tz    = 'Asia/Tokyo';
        $now   = Carbon::now($tz);
        $year  = (int) $now->format('Y');
        $month = 10; // 10月

        // User 1〜10 の 9月レポートに対して、1〜30日の空行を作成
        for ($userId = 1; $userId <= 10; $userId++) {
            $reportId = DB::table('expense_reports')
                ->where('user_id', $userId)
                ->where('year', $year)
                ->where('month', $month)
                ->value('id');

            if (!$reportId) {
                continue; // レポート未作成ならスキップ
            }

            $rows = [];
            for ($day = 1; $day <= 30; $day++) {
                $date = Carbon::create($year, $month, $day, 0, 0, 0, $tz)->toDateString();

                // 既に該当日の行が存在するなら作らない（再実行安全）
                $exists = DB::table('expenses')
                    ->where('expense_report_id', $reportId)
                    ->whereDate('expense_date', $date)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $rows[] = [
                    'expense_report_id' => $reportId,
                    'expense_date'      => $date,
                    'station_from'      => null,
                    'station_to'        => null,
                    'note'              => null,
                    'cost'              => 0,
                    // 明示的にデフォルトを入れておく（DB既定でも可）
                    'trip_type'         => ExpenseTripType::ROUND_TRIP->value,
                    'category'          => ExpenseCategory::REGULAR->value,
                    'commuter_pass_id'  => null,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            if (!empty($rows)) {
                DB::table('expenses')->insert($rows);
            }
        }
    }
}
