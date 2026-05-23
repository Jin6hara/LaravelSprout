<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_date'    => fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d'),
            'status'        => 'pending',
            'type'          => 'regular_time',
            'district_id'   => District::factory(),
            'department_id' => Department::factory(),
            // original_user_id / assigned_user_id は nullable のためテストで個別に指定
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
