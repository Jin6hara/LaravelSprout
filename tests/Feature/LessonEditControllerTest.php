<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\ScheduleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonEditControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_searchable_paginated_lesson_list(): void
    {
        $admin = User::factory()->admin()->create();

        Lesson::factory()->count(35)->create();
        Lesson::factory()->create([
            'lesson_name' => 'Target Conversation',
            'lesson_code' => 'TARGET01',
            'lesson_minute' => 45,
            'lesson_type' => 'Adults',
        ]);

        $this->actingAs($admin)
            ->get(route('data.lessons.index', ['q' => 'target', 'per_page' => 60]))
            ->assertOk()
            ->assertSee('Target Conversation')
            ->assertSee('TARGET01')
            ->assertSee('60');
    }

    public function test_admin_can_create_update_and_delete_lesson(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('data.lessons.store'), [
                'lesson_name' => 'New Lesson',
                'lesson_code' => 'NEW01',
                'lesson_minute' => 30,
                'lesson_type' => 'Kids',
                'note' => 'Starter',
                'ps_unique_lesson_code' => 'PS-NEW',
                'fm_lesson_code' => 'FM-NEW',
            ])
            ->assertRedirect(route('data.lessons.index'));

        $lesson = Lesson::where('lesson_code', 'NEW01')->firstOrFail();

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'lesson_name' => 'New Lesson',
            'lesson_minute' => 30,
        ]);

        $this->actingAs($admin)
            ->put(route('data.lessons.update', $lesson), [
                'lesson_name' => 'Updated Lesson',
                'lesson_code' => 'UPD01',
                'lesson_minute' => 45,
                'lesson_type' => 'Adults',
                'note' => 'Updated note',
                'ps_unique_lesson_code' => 'PS-UPD',
                'fm_lesson_code' => 'FM-UPD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
            'lesson_name' => 'Updated Lesson',
            'lesson_code' => 'UPD01',
            'lesson_minute' => 45,
        ]);

        $this->actingAs($admin)
            ->delete(route('data.lessons.destroy', $lesson))
            ->assertRedirect();

        $this->assertDatabaseMissing('lessons', [
            'id' => $lesson->id,
        ]);
    }

    public function test_admin_cannot_delete_lesson_used_by_schedule_details(): void
    {
        $admin = User::factory()->admin()->create();
        $lesson = Lesson::factory()->create();
        ScheduleDetail::factory()->create([
            'lesson_id' => $lesson->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('data.lessons.destroy', $lesson))
            ->assertRedirect()
            ->assertSessionHas('toast_errors');

        $this->assertDatabaseHas('lessons', [
            'id' => $lesson->id,
        ]);
    }
}
