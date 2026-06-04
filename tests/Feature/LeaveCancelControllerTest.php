<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\Event;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveCancelControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeaveWithGeneratedShift(): array
    {
        $district = District::factory()->create();
        $department = Department::factory()->create();

        $user = User::factory()->general()->create([
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        $leave = Leave::factory()->create([
            'user_id' => $user->id,
            'start_date' => '2026-06-13',
            'kind' => 'paid',
            'excused' => 'excused',
            'status' => 'pending',
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        $event = Event::factory()->create([
            'district_id' => $district->id,
            'department_id' => $department->id,
            'event_date' => '2026-06-13',
            'original_user_id' => $user->id,
            'school_name' => 'Cancel Generated School',
            'status' => 'pending',
            'type' => 'regular_time',
            'source_leave_id' => $leave->id,
        ]);

        return [$user, $leave, $event];
    }

    public function test_leave_cancel_requires_generated_shift_action_when_shift_exists(): void
    {
        [$user, $leave, $event] = $this->makeLeaveWithGeneratedShift();

        $this->actingAs($user)
            ->post(route('leaves.cancel', $leave))
            ->assertRedirect()
            ->assertSessionHas('toast_errors');

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'source_leave_id' => $leave->id,
        ]);
    }

    public function test_leave_cancel_can_keep_generated_shift(): void
    {
        [$user, $leave, $event] = $this->makeLeaveWithGeneratedShift();

        $this->actingAs($user)
            ->post(route('leaves.cancel', $leave), [
                'generated_shift_action' => 'detach',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'source_leave_id' => null,
        ]);
    }

    public function test_leave_cancel_can_delete_generated_shift(): void
    {
        [$user, $leave, $event] = $this->makeLeaveWithGeneratedShift();

        $this->actingAs($user)
            ->post(route('leaves.cancel', $leave), [
                'generated_shift_action' => 'delete',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast');

        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }
}
