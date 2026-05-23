<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RestPattern>
 */
class RestPatternFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word() . ' Pattern',
            'code' => strtoupper(fake()->unique()->bothify('RP_??##')),
        ];
    }
}
