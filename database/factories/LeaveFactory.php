<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\District;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Leave>
 */
class LeaveFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'start_date'    => fake()->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'end_date'      => null,
            'reason'        => null,
            'kind'          => 'paid',
            'excused'       => 'excused',
            'status'        => 'approved',
            'district_id'   => District::factory(),
            'department_id' => Department::factory(),
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
