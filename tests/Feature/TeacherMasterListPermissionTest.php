<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\EmploymentTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Teacher Master List の permission テスト
 *
 * シナリオ:
 * 1. teacher.viewAll を持たない general は /user/master-list に入れない
 * 2. teacher.viewAll を持つ trainer は /user/master-list に入れる
 * 3. trainer の一覧は自分と同じ district / department のユーザーだけ表示する
 */
class TeacherMasterListPermissionTest extends TestCase
{
    use RefreshDatabase;

    private function giveTeacherViewAllTo(string $roleName): void
    {
        $permission = Permission::firstOrCreate(['name' => 'teacher.viewAll', 'guard_name' => 'web']);
        Role::findByName($roleName, 'web')->givePermissionTo($permission);
    }

    private function makeActive(User $user): void
    {
        EmploymentTerm::create([
            'user_id' => $user->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => null,
            'type_name' => 'Full-time',
            'type_code' => 'FT',
        ]);
    }

    public function test_general_user_without_teacher_view_all_cannot_view_master_list(): void
    {
        $general = User::factory()->general()->create();

        $this->actingAs($general)
            ->get(route('user.master_list'))
            ->assertRedirect(route('welcome'));
    }

    public function test_trainer_with_teacher_view_all_can_view_master_list(): void
    {
        $this->giveTeacherViewAllTo('trainer');

        $trainer = User::factory()->create();
        $trainer->assignRole('trainer');

        $this->actingAs($trainer)
            ->get(route('user.master_list'))
            ->assertOk();
    }

    public function test_trainer_master_list_is_limited_to_own_district_and_department(): void
    {
        $this->giveTeacherViewAllTo('trainer');

        $district = District::factory()->create();
        $department = Department::factory()->create();
        $otherDistrict = District::factory()->create();
        $otherDepartment = Department::factory()->create();

        $trainer = User::factory()->create([
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);
        $trainer->assignRole('trainer');
        $this->makeActive($trainer);

        $inScopeUser = User::factory()->create([
            'district_id' => $district->id,
            'department_id' => $department->id,
            'employee_code' => 'IN001',
        ]);
        $this->makeActive($inScopeUser);

        $outsideUser = User::factory()->create([
            'district_id' => $otherDistrict->id,
            'department_id' => $otherDepartment->id,
            'employee_code' => 'OUT001',
        ]);
        $this->makeActive($outsideUser);

        $this->actingAs($trainer)
            ->get(route('user.master_list'))
            ->assertOk()
            ->assertSee($inScopeUser->employee_code)
            ->assertDontSee($outsideUser->employee_code);
    }
}
