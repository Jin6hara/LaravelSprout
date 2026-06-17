<?php

/**
 * 在籍中の従業員全員に対して、指定月の経費レポートと日次経費行を一括生成するコマンド。
 */
//ここでphp artisan expenses:generate-monthly {year} {month} コマンドを定義

namespace App\Console\Commands;

use App\Models\User;
use App\Models\EmploymentTerm;
use App\Models\ExpenseReport;
use App\Models\Expense;
use App\Enums\ExpenseReportStatus;
use App\Enums\ExpenseTripType;
use App\Enums\ExpenseCategory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyExpenses extends Command
{
    /**
     * 実行コマンド名
     *
     * 例）
     * php artisan expenses:generate-monthly           ← デフォルト（今月分）
     * php artisan expenses:generate-monthly 2025 03   ← 明示的に指定
     */
    protected $signature = 'expenses:generate-monthly {year?} {month?}';
    protected $description = 'Generate monthly expense_reports and expenses for active employees';

    public function handle(): int
    {
        // 引数指定がなければ「今の年月」
        $now  = now();
        $year = (int) ($this->argument('year')  ?? $now->year);
        $month = (int) ($this->argument('month') ?? $now->month);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth   = (clone $startOfMonth)->endOfMonth();

        $this->info("Generating expense reports for {$year}-{$month} ...");

        // 対象となる employment_terms：この月に1日でも在籍している人
        $termQuery = EmploymentTerm::query()
            ->where('start_date', '<=', $endOfMonth->toDateString())
            ->where(function ($q) use ($startOfMonth) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $startOfMonth->toDateString());
            })
            ->with('user'); // user リレーション前提

        $terms = $termQuery->get();

        if ($terms->isEmpty()) {
            $this->info('No active employment terms found for target month.');
            return Command::SUCCESS;
        }

        // user 単位でユニークに
        $users = $terms->pluck('user')->filter()->unique('id');

        $this->info("Target users: {$users->count()}");

        $daysInMonth = (int) $startOfMonth->daysInMonth;

        DB::transaction(function () use ($users, $year, $month, $startOfMonth, $daysInMonth) {
            foreach ($users as $user) {
                // 既存レポートがあればスキップ（重複ユニーク制約を守る）
                $report = ExpenseReport::where('user_id', $user->id)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->first();

                if (!$report) {
                    $report = ExpenseReport::create([
                        'user_id'               => $user->id,
                        'employee_code'         => $user->employee_code ?? null,
                        'employee_family_name'  => $user->family_name,
                        'employee_first_name'   => $user->first_name,
                        'employee_middle_name'  => $user->middle_name ?? null,
                        'year'                  => $year,
                        'month'                 => $month,
                        'status'                => ExpenseReportStatus::DRAFT,
                        'submitted_at'          => null,
                        'approved_at'           => null,
                        'paid_at'               => null,
                        'total_amount'          => 0,
                    ]);
                }

                // 既存の expenses を確認（途中まで手入力されている可能性もある）
                $existingDates = Expense::where('expense_report_id', $report->id)
                    ->pluck('expense_date')
                    ->map(fn($d) => Carbon::parse($d)->toDateString())
                    ->all();

                // 対象月の各日について、存在しなければ1行作成
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = (clone $startOfMonth)->day($day)->toDateString();

                    if (in_array($date, $existingDates, true)) {
                        continue; // 既にある日はスキップ
                    }

                    Expense::create([
                        'expense_report_id' => $report->id,
                        'expense_date'      => $date,
                        'seq'               => 100,
                        'station_from'      => null,
                        'station_to'        => null,
                        'note'              => null,
                        'cost'              => 0,
                        'trip_type'         => ExpenseTripType::ROUND_TRIP->value, // ← type は round_trip
                        'category'          => ExpenseCategory::REGULAR->value,
                        'commuter_pass_id'  => null,
                    ]);
                }
            }
        });

        $this->info('Done.');

        return Command::SUCCESS;
    }
}
