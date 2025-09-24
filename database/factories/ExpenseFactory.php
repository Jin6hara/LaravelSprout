<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseTripType;
use App\Models\Expense;
use App\Models\ExpenseReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        $report = ExpenseReport::inRandomOrder()->first()
            ?? ExpenseReport::factory()->create();
        $date = now()->startOfMonth()->addDays(rand(0, now()->daysInMonth - 1));

        return [
            'user_id'          => $report->user_id,
            'expense_report_id' => $report->id,
            'expense_date'     => $date->toDateString(),
            'station_from'     => '梅田',
            'station_to'       => '心斎橋',
            'note'             => $this->faker->optional()->sentence(),
            'cost'             => [180, 200, 240, 320][array_rand([0, 1, 2, 3])] * (rand(0, 1) ? 1 : 2),
            'trip_type'        => ExpenseTripType::ROUND_TRIP,
            'category'         => ExpenseCategory::REGULAR,
            'commuter_pass_id' => null,
        ];
    }
}
