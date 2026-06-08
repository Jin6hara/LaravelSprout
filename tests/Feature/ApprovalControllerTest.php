<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Department;
use App\Models\District;
use App\Models\Event;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    private function makeLeaveApprovalWithGeneratedShift(): array
    {
        $district = District::factory()->create();
        $department = Department::factory()->create();

        $requester = User::factory()->general()->create([
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        $leave = Leave::factory()->create([
            'user_id' => $requester->id,
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
            'original_user_id' => $requester->id,
            'school_name' => 'Approval Generated School',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'Lesson' => 'ALP L1',
            'status' => 'pending',
            'type' => 'regular_time',
            'source_leave_id' => $leave->id,
        ]);

        $approval = ApprovalRequest::create([
            'approvable_type' => Leave::class,
            'approvable_id' => $leave->id,
            'title' => 'Paid Leave Request',
            'requested_by_id' => $requester->id,
            'current_state' => 'pending',
            'metadata' => [
                'kind' => 'paid',
                'date' => '2026-06-13',
                'target_user_id' => $requester->id,
            ],
        ]);

        return [$approval, $leave, $event];
    }

    public function test_super_admin_can_confirm_generated_shifts_before_denying_leave_request(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        [$approval] = $this->makeLeaveApprovalWithGeneratedShift();

        $this->actingAs($superAdmin)
            ->get(route('approvals.show', $approval))
            ->assertOk()
            ->assertSee('Generated Shift Confirmation')
            ->assertSee('Keep Shift')
            ->assertSee('Delete Shift')
            ->assertSee('generated_shift_action')
            ->assertSee('Approval Generated School')
            ->assertSee('ALP L1');
    }

    public function test_super_admin_must_confirm_generated_shift_action_before_denying_leave_request(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        [$approval, $leave, $event] = $this->makeLeaveApprovalWithGeneratedShift();

        $this->actingAs($superAdmin)
            ->post(route('approvals.deny', $approval), [
                'comment' => 'Not approved.',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast_errors');

        $this->assertDatabaseHas('approval_requests', [
            'id' => $approval->id,
            'current_state' => 'pending',
        ]);
        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'source_leave_id' => $leave->id,
        ]);
    }
}
