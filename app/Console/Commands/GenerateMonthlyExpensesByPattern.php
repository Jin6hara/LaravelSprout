<?php

namespace App\Console\Commands;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseReportStatus;
use App\Enums\ExpenseTripType;
use App\Models\CommuterPass;
use App\Models\EmploymentTerm;
use App\Models\Expense;
use App\Models\ExpenseReport;
use App\Models\User;
use App\Services\Calendar\CalendarResolver;
use App\Services\CommutingExpenses\CommutePatternService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMonthlyExpensesByPattern extends Command
{
    protected $signature = 'expenses:generate-monthly-by-pattern {year?} {month?}';

    protected $description = 'Generate monthly expense_reports and expenses using valid commute patterns when available';

    public function handle(CommutePatternService $patterns, CalendarResolver $calendarResolver): int
    {
        $now = now();
        $year = (int) ($this->argument('year') ?? $now->year);
        $month = (int) ($this->argument('month') ?? $now->month);

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = (clone $startOfMonth)->endOfMonth();

        $this->info("Generating expense reports by pattern for {$year}-{$month} ...");

        $terms = EmploymentTerm::query()
            ->where('start_date', '<=', $endOfMonth->toDateString())
            ->where(function ($q) use ($startOfMonth) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $startOfMonth->toDateString());
            })
            ->with('user')
            ->get();

        if ($terms->isEmpty()) {
            $this->info('No active employment terms found for target month.');

            return Command::SUCCESS;
        }

        $users = $terms->pluck('user')->filter()->unique('id');
        $daysInMonth = (int) $startOfMonth->daysInMonth;

        $this->info("Target users: {$users->count()}");

        DB::transaction(function () use ($users, $year, $month, $startOfMonth, $endOfMonth, $daysInMonth, $patterns, $calendarResolver) {
            foreach ($users as $user) {
                $report = ExpenseReport::query()
                    ->where('user_id', $user->id)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->first();

                if (! $report) {
                    $report = ExpenseReport::create([
                        'user_id' => $user->id,
                        'employee_code' => $user->employee_code ?? null,
                        'employee_family_name' => $user->family_name,
                        'employee_first_name' => $user->first_name,
                        'employee_middle_name' => $user->middle_name ?? null,
                        'year' => $year,
                        'month' => $month,
                        'status' => ExpenseReportStatus::DRAFT,
                        'submitted_at' => null,
                        'approved_at' => null,
                        'paid_at' => null,
                        'total_amount' => 0,
                    ]);
                }

                $existingDates = Expense::query()
                    ->where('expense_report_id', $report->id)
                    ->pluck('expense_date')
                    ->map(fn ($d) => Carbon::parse($d)->toDateString())
                    ->all();

                $activePatterns = $patterns->forPeriod($user, $startOfMonth, $endOfMonth);
                $workOnMap = $this->buildWorkOnMap($calendarResolver, $user, $startOfMonth, $endOfMonth);
                $passActiveMap = $this->buildPassActiveMap($user, $startOfMonth, $endOfMonth);

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $date = (clone $startOfMonth)->day($day);
                    $dateString = $date->toDateString();

                    if (in_array($dateString, $existingDates, true)) {
                        continue;
                    }

                    if (! ($workOnMap[$dateString] ?? false) || ($passActiveMap[$dateString] ?? false)) {
                        $this->createBlankExpense($report, $dateString);

                        continue;
                    }

                    $pattern = $activePatterns->first(function ($pattern) use ($dateString) {
                        return $pattern->valid_from->toDateString() <= $dateString
                            && $pattern->valid_to->toDateString() >= $dateString;
                    });

                    $dow = $date->format('D');
                    $legs = $pattern
                        ? $pattern->legs
                            ->filter(fn ($leg) => (is_object($leg->dow) ? $leg->dow->value : $leg->dow) === $dow)
                            ->sortBy([['seq', 'asc'], ['id', 'asc']])
                            ->values()
                        : collect();

                    if ($legs->isNotEmpty()) {
                        foreach ($legs as $index => $leg) {
                            Expense::create([
                                'expense_report_id' => $report->id,
                                'expense_date' => $dateString,
                                'seq' => $leg->seq ?: (($index + 1) * 100),
                                'station_from' => $leg->station_from,
                                'station_to' => $leg->station_to,
                                'note' => $leg->note,
                                'cost' => (int) $leg->cost,
                                'trip_type' => is_object($leg->trip_type) ? $leg->trip_type->value : $leg->trip_type,
                                'category' => ExpenseCategory::REGULAR->value,
                                'commuter_pass_id' => null,
                            ]);
                        }

                        continue;
                    }

                    $this->createBlankExpense($report, $dateString);
                }
            }
        });

        $this->info('Done.');

        return Command::SUCCESS;
    }

    private function buildWorkOnMap(CalendarResolver $calendarResolver, User $user, Carbon $start, Carbon $end): array
    {
        $events = $calendarResolver->build($user, $start, $end);
        $daily = [];

        foreach ($events as $event) {
            $dateKey = $event['dateKey'] ?? (isset($event['start'])
                ? (is_string($event['start']) ? substr($event['start'], 0, 10) : Carbon::parse($event['start'])->toDateString())
                : null);

            if (! $dateKey) {
                continue;
            }

            $daily[$dateKey] ??= ['has_on' => false];

            if (strtolower((string) ($event['display'] ?? '')) === 'on') {
                $daily[$dateKey]['has_on'] = true;
            }
        }

        $workOnMap = [];
        foreach ($daily as $date => $flags) {
            $workOnMap[$date] = $flags['has_on'];
        }

        return $workOnMap;
    }

    private function buildPassActiveMap(User $user, Carbon $start, Carbon $end): array
    {
        $passes = CommuterPass::query()
            ->where('user_id', $user->id)
            ->whereDate('date_from', '<=', $end->toDateString())
            ->whereDate('date_to', '>=', $start->toDateString())
            ->get(['date_from', 'date_to']);

        $passActiveMap = [];

        foreach ($passes as $pass) {
            $from = Carbon::parse($pass->date_from)->max($start);
            $to = Carbon::parse($pass->date_to)->min($end);

            for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
                $passActiveMap[$date->toDateString()] = true;
            }
        }

        return $passActiveMap;
    }

    private function createBlankExpense(ExpenseReport $report, string $date): void
    {
        Expense::create([
            'expense_report_id' => $report->id,
            'expense_date' => $date,
            'seq' => 100,
            'station_from' => null,
            'station_to' => null,
            'note' => null,
            'cost' => 0,
            'trip_type' => ExpenseTripType::ROUND_TRIP->value,
            'category' => ExpenseCategory::REGULAR->value,
            'commuter_pass_id' => null,
        ]);
    }
}
