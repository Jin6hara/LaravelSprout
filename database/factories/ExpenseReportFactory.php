<?php

namespace Database\Factories;

use App\Enums\ExpenseReportStatus;
use App\Models\ExpenseReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseReportFactory extends Factory
{
    protected $model = ExpenseReport::class;

    public function definition(): array
    {
        $user = User::inRandomOrder()->first() ?? User::factory()->create();

        $y = now()->year;
        $m = now()->month;

        return [
            'user_id'                     => $user->id,
            'employee_code'               => $user->employee_code ?? str_pad((string)$user->id, 4, '0', STR_PAD_LEFT),
            'employee_family_name'        => $user->family_name ?? $this->faker->lastName(),
            'employee_first_middle_name'  => $user->first_middle_name ?? $this->faker->firstName(),
            'year'                        => $y,
            'month'                       => $m,
            'status'                      => ExpenseReportStatus::DRAFT,
            'total_amount'                => 0,
        ];
    }
}
