<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\District>
 */
class DistrictFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => fake()->city(),
            'parent_id' => null,
        ];
    }
}
