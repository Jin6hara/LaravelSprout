<?php

namespace Database\Factories;

use App\Models\RestPattern;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserRestPattern>
 */
class UserRestPatternFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'          => User::factory(),
            'rest_pattern_id'  => RestPattern::factory(),
            'start_date'       => now()->toDateString(),
            'end_date'         => null,
        ];
    }
}
