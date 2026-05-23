<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\School>
 */
class SchoolFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_code' => strtoupper(fake()->unique()->bothify('SC####')),
            'school_name' => fake()->company() . ' School',
            'is_active'   => true,
            'district_id' => null,
        ];
    }
}
