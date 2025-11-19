<?php

namespace App\Console\Commands;

use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanUpEmptyExpenses extends Command
{
    /**
     * 実行コマンド名
     *
     * 例）
     * php artisan expenses:cleanup-empty           ← デフォルト（2ヶ月前）
     * php artisan expenses:cleanup-empty 2025 03   ← 明示的に指定
     */
    protected $signature = 'expenses:cleanup-empty {year?} {month?}';
    protected $description = 'Delete empty expense rows (note is null & cost = 0) for the target month';

    public function handle(): int
    {
        $now = now();

        // year/month 引数が無ければ「2ヶ月前の年月」を対象にする
        if ($this->argument('year') && $this->argument('month')) {
            $year  = (int) $this->argument('year');
            $month = (int) $this->argument('month');
        } else {
            $target = $now->copy()->subMonthsNoOverflow(2); // 今が5月なら3月
            $year   = (int) $target->year;
            $month  = (int) $target->month;
        }

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth   = (clone $startOfMonth)->endOfMonth()->endOfDay();

        $this->info("Cleaning empty expenses for {$year}-{$month} ...");

        $query = Expense::query()
            ->whereBetween('expense_date', [
                $startOfMonth->toDateString(),
                $endOfMonth->toDateString(),
            ])
            ->whereNull('note')
            ->where('cost', 0);

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No empty expenses found for target month.');
            return Command::SUCCESS;
        }

        $this->info("Found {$count} empty expense rows. Deleting...");

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} expense rows.");

        return Command::SUCCESS;
    }
}
