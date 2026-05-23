<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\ScheduleLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduleDetail>
 */
class ScheduleDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'schedule_line_id' => ScheduleLine::factory(),
            'start_time'       => '09:00:00',
            'lesson_id'        => Lesson::factory(),
            'effective_start'  => now()->toDateString(),
            'effective_end'    => null,
        ];
    }
}
