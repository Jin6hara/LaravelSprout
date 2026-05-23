<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduleLine>
 */
class ScheduleLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => null,
            'parent_line_id'  => null,
            'total_minutes'   => 480,
            'dow'             => fake()->numberBetween(0, 6),
            'school_name'     => fake()->company() . ' School',
            'start_time'      => '09:00:00',
            'end_time'        => '17:00:00',
            'effective_start' => now()->toDateString(),
            'effective_end'   => now()->addYear()->toDateString(),
            'district_id'     => District::factory(),
            'department_id'   => Department::factory(),
        ];
    }

    /** scope を外部から受け取った District/Department に固定する */
    public function forScope(int $districtId, int $departmentId): static
    {
        return $this->state(fn () => [
            'district_id'   => $districtId,
            'department_id' => $departmentId,
        ]);
    }
}
