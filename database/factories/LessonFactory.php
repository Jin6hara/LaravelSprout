<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lesson>
 */
class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_name'   => fake()->word() . ' Lesson',
            'lesson_code'   => strtoupper(fake()->unique()->bothify('LS####')),
            'lesson_minute' => fake()->randomElement([30, 40, 45, 60]),
            'lesson_type'   => null,
        ];
    }
}
