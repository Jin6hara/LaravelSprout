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
        $tz      = 'Asia/Tokyo';
        $now     = Carbon::now($tz);
        $year    = (int) $now->format('Y');
        $months  = [9, 10, 11]; // 9〜11月分をまとめて作成

        // User 1〜10 の各月レポートに対して、その月の全日（該当日数）の空行を作成
        for ($userId = 1; $userId <= 10; $userId++) {
            foreach ($months as $month) {
                // 対象レポート（user_id + year + month）
                $reportId = DB::table('expense_reports')
                    ->where('user_id', $userId)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->value('id');

                if (!$reportId) {
                    continue; // レポート未作成ならスキップ
                }

                $startOfMonth = Carbon::create($year, $month, 1, 0, 0, 0, $tz)->startOfDay();
                $endOfMonth   = (clone $startOfMonth)->endOfMonth();
                $daysInMonth  = (int) $startOfMonth->daysInMonth;

                // その月に既に存在している expense の日付を一括取得（再実行安全 & N+1回避）
                $existingDates = DB::table('expenses')
                    ->where('expense_report_id', $reportId)
                    ->whereBetween('expense_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
                    ->pluck('expense_date')
                    ->all();

                $existing = array_flip($existingDates); // O(1)判定用

                $rows = [];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = Carbon::create($year, $month, $day, 0, 0, 0, $tz)->toDateString();

                    if (isset($existing[$date])) {
                        continue; // 既存なら作らない（再実行安全）
                    }

                    $rows[] = [
                        'expense_report_id' => $reportId,
                        'expense_date'      => $date,
                        'station_from'      => null,
                        'station_to'        => null,
                        'note'              => null,
                        'cost'              => 0,
                        'trip_type'         => ExpenseTripType::ROUND_TRIP->value,
                        'category'          => ExpenseCategory::REGULAR->value,
                        'commuter_pass_id'  => null,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }

                if (!empty($rows)) {
                    // まとめてINSERT（件数次第ではチャンクに分けてもOK）
                    DB::table('expenses')->insert($rows);
                }
            }
        }
    }
}
