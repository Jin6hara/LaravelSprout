<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\RoleChange;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 権限変更申請フロー のテスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [🔴 applyDomainEffect() — 承認後の反映ロジック]
 *   1. general 承認後: district_id / department_id が更新される
 *   2. general 承認後: user_management_scopes が全削除される
 *   3. admin 承認後: district_id / department_id が更新される
 *   4. admin 承認後: user_management_scopes が申請内容で完全同期される
 *   5. admin 承認後: 申請に含まれない旧スコープは削除される
 *   6. admin → general 降格後: 既存スコープが全削除される
 *
 * [🟠 バリデーション — HTTP POST]
 *   7.  district_id 未送信 → 422
 *   8.  department_id 未送信 → 422
 *   9.  general + scopes あり → 422（一般ユーザーに管理範囲は不可）
 *  10.  admin + scopes なし → 422（管理範囲は1件以上必須）
 *  11.  admin + 重複 scopes → 422
 *  12.  admin + 存在しない district_id の scope → 422
 *  13.  general 正常申請 → RoleChange / ApprovalRequest が生成され users・ロールは変わらない
 *  14.  admin 正常申請 → RoleChange に scopes が保存され users・ロールは変わらない
 * ─────────────────────────────────────────────────────────────────────────────
 */
class RoleChangeTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // ヘルパー
    // =========================================================================

    /** actor (super_admin) がターゲットを管理できるようスコープをセットアップ */
    private function setUpActorAndTarget(): array
    {
        $district   = District::factory()->create();
        $department = Department::factory()->create();

        $actor = User::factory()->superAdmin()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);
        UserManagementScope::factory()->create([
            'user_id'       => $actor->id,
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        // ターゲットが同一地区・部署に属することで policy を通過させる
        $target = User::factory()->general()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        return [$actor, $target, $district, $department];
    }

    /** RoleChange を直接生成し applyDomainEffect() を呼ぶ（HTTP を介さない） */
    private function buildAndApply(
        User    $target,
        string  $role,
        ?int    $districtId,
        ?int    $departmentId,
        array   $scopes = []
    ): RoleChange {
        $requester = User::factory()->superAdmin()->create();
        $rc = RoleChange::create([
            'user_id'         => $target->id,
            'requested_role'  => $role,
            'reason'          => 'test',
            'requested_by_id' => $requester->id,
            'status'          => 'pending',
            'district_id'     => $districtId,
            'department_id'   => $departmentId,
            'scopes'          => $scopes,
        ]);
        $rc->applyDomainEffect();
        return $rc;
    }

    // =========================================================================
    // 🔴 applyDomainEffect() — 承認後の反映ロジック
    // =========================================================================

    /** 1. general 承認後: 所属地区・部署が更新される */
    public function test_general_approval_updates_belonging(): void
    {
        $oldDistrict   = District::factory()->create();
        $newDistrict   = District::factory()->create();
        $oldDepartment = Department::factory()->create();
        $newDepartment = Department::factory()->create();

        $target = User::factory()->admin()->create([
            'district_id'   => $oldDistrict->id,
            'department_id' => $oldDepartment->id,
        ]);

        $this->buildAndApply($target, 'general', $newDistrict->id, $newDepartment->id);

        $this->assertDatabaseHas('users', [
            'id'            => $target->id,
            'district_id'   => $newDistrict->id,
            'department_id' => $newDepartment->id,
        ]);
        $this->assertTrue($target->fresh()->hasRole('general'));
    }

    /** 2. general 承認後: user_management_scopes が全削除される */
    public function test_general_approval_deletes_all_management_scopes(): void
    {
        $district    = District::factory()->create();
        $department1 = Department::factory()->create();
        $department2 = Department::factory()->create();
        $department3 = Department::factory()->create();

        $target = User::factory()->admin()->create();
        foreach ([$department1, $department2, $department3] as $dept) {
            UserManagementScope::factory()->create([
                'user_id'       => $target->id,
                'district_id'   => $district->id,
                'department_id' => $dept->id,
            ]);
        }

        $this->buildAndApply($target, 'general', $district->id, $department1->id);

        $this->assertDatabaseMissing('user_management_scopes', ['user_id' => $target->id]);
    }

    /** 3. admin 承認後: 所属地区・部署が更新される */
    public function test_admin_approval_updates_belonging(): void
    {
        $oldDistrict   = District::factory()->create();
        $newDistrict   = District::factory()->create();
        $oldDepartment = Department::factory()->create();
        $newDepartment = Department::factory()->create();

        $target = User::factory()->general()->create([
            'district_id'   => $oldDistrict->id,
            'department_id' => $oldDepartment->id,
        ]);

        $this->buildAndApply($target, 'admin', $newDistrict->id, $newDepartment->id, [
            ['district_id' => $newDistrict->id, 'department_id' => $newDepartment->id],
        ]);

        $this->assertDatabaseHas('users', [
            'id'            => $target->id,
            'district_id'   => $newDistrict->id,
            'department_id' => $newDepartment->id,
        ]);
        $this->assertTrue($target->fresh()->hasRole('admin'));
    }

    /** 4. admin 承認後: user_management_scopes が申請内容で完全同期される */
    public function test_admin_approval_syncs_management_scopes(): void
    {
        $d1 = District::factory()->create();
        $d2 = District::factory()->create();
        $p  = Department::factory()->create();

        $target = User::factory()->general()->create();

        $this->buildAndApply($target, 'admin', $d1->id, $p->id, [
            ['district_id' => $d1->id, 'department_id' => $p->id],
            ['district_id' => $d2->id, 'department_id' => $p->id],
        ]);

        $this->assertCount(2, $target->fresh()->managementScopes);
        $this->assertDatabaseHas('user_management_scopes', [
            'user_id' => $target->id, 'district_id' => $d1->id, 'department_id' => $p->id,
        ]);
        $this->assertDatabaseHas('user_management_scopes', [
            'user_id' => $target->id, 'district_id' => $d2->id, 'department_id' => $p->id,
        ]);
    }

    /** 5. admin 承認後: 申請に含まれない旧スコープは削除される */
    public function test_admin_approval_removes_old_scopes_not_in_request(): void
    {
        $oldDistrict = District::factory()->create();
        $newDistrict = District::factory()->create();
        $dept        = Department::factory()->create();

        $target = User::factory()->admin()->create();
        UserManagementScope::factory()->create([
            'user_id'       => $target->id,
            'district_id'   => $oldDistrict->id,
            'department_id' => $dept->id,
        ]);

        $this->buildAndApply($target, 'admin', $newDistrict->id, $dept->id, [
            ['district_id' => $newDistrict->id, 'department_id' => $dept->id],
        ]);

        $this->assertDatabaseMissing('user_management_scopes', [
            'user_id' => $target->id, 'district_id' => $oldDistrict->id,
        ]);
        $this->assertDatabaseHas('user_management_scopes', [
            'user_id' => $target->id, 'district_id' => $newDistrict->id,
        ]);
    }

    /** 6. admin → general 降格後: 既存スコープが全削除される */
    public function test_demotion_from_admin_to_general_clears_scopes(): void
    {
        $district    = District::factory()->create();
        $department1 = Department::factory()->create();
        $department2 = Department::factory()->create();

        $target = User::factory()->admin()->create();
        foreach ([$department1, $department2] as $dept) {
            UserManagementScope::factory()->create([
                'user_id'       => $target->id,
                'district_id'   => $district->id,
                'department_id' => $dept->id,
            ]);
        }

        $this->buildAndApply($target, 'general', $district->id, $department1->id);

        $this->assertDatabaseMissing('user_management_scopes', ['user_id' => $target->id]);
        $this->assertTrue($target->fresh()->hasRole('general'));
    }

    // =========================================================================
    // 🟠 バリデーション — HTTP POST
    // =========================================================================

    /** 7. district_id 未送信 → 422 */
    public function test_district_id_is_required(): void
    {
        [$actor, $target] = $this->setUpActorAndTarget();

        $this->actingAs($actor)
            ->postJson(route('roleChange.apply', $target), [
                'role'          => 'general',
                'reason'        => 'test',
                'department_id' => Department::factory()->create()->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['district_id']);
    }

    /** 8. department_id 未送信 → 422 */
    public function test_department_id_is_required(): void
    {
        [$actor, $target] = $this->setUpActorAndTarget();

        $this->actingAs($actor)
            ->postJson(route('roleChange.apply', $target), [
                'role'        => 'general',
                'reason'      => 'test',
                'district_id' => District::factory()->create()->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id']);
    }

    /** 9. general + scopes あり → 422（一般ユーザーに管理範囲は不可） */
    public function test_general_role_rejects_scopes(): void
    {
        [$actor, $target, $district, $department] = $this->setUpActorAndTarget();

        $this->actingAs($actor)
            ->postJson(route('roleChange.apply', $target), [
                'role'          => 'general',
                'reason'        => 'test',
                'district_id'   => $district->id,
                'department_id' => $department->id,
                'scopes'        => [
                    ['district_id' => $district->id, 'department_id' => $department->id],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scopes']);
    }

    /** 10. admin + scopes なし → 422（管理範囲は1件以上必須） */
    public function test_admin_role_requires_at_least_one_scope(): void
    {
        [$actor, $target, $district, $department] = $this->setUpActorAndTarget();

        $this->actingAs($actor)
            ->postJson(route('roleChange.apply', $target), [
                'role'          => 'admin',
                'reason'        => 'test',
                'district_id'   => $district->id,
                'department_id' => $department->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scopes']);
    }

    /** 11. admin + 重複 scopes → 422 */
    public function test_duplicate_scopes_are_rejected(): void
    {
        [$actor, $target, $district, $department] = $this->setUpActorAndTarget();

        $this->actingAs($actor)
            ->postJson(route('roleChange.apply', $target), [
                'role'          => 'admin',
                'reason'        => 'test',
                'district_id'   => $district->id,
                'department_id' => $department->id,
                'scopes'        => [
                    ['district_id' => $district->id, 'department_id' => $department->id],
                    ['district_id' => $district->id, 'department_id' => $department->id],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scopes.1']);
    }

    /** 12. admin + 存在しない district_id の scope → 422 */
    public function test_nonexistent_district_in_scope_is_rejected(): void
    {
        [$actor, $target, $district, $department] = $this->setUpActorAndTarget();

        $this->actingAs($actor)
            ->postJson(route('roleChange.apply', $target), [
                'role'          => 'admin',
                'reason'        => 'test',
                'district_id'   => $district->id,
                'department_id' => $department->id,
                'scopes'        => [
                    ['district_id' => 99999, 'department_id' => $department->id],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['scopes.0.district_id']);
    }

    /** 13. general 正常申請 → RoleChange / ApprovalRequest 生成・users とロールは変わらない */
    public function test_general_apply_creates_workflow_without_changing_user(): void
    {
        [$actor, $target, $district, $department] = $this->setUpActorAndTarget();
        $originalRole = $target->getRoleNames()->first();

        $this->actingAs($actor)
            ->postJson(route('roleChange.apply', $target), [
                'role'          => 'general',
                'reason'        => '異動のため',
                'district_id'   => $district->id,
                'department_id' => $department->id,
            ])
            ->assertRedirect();

        // RoleChange が生成されている
        $this->assertDatabaseHas('role_changes', [
            'user_id'        => $target->id,
            'requested_role' => 'general',
            'status'         => 'pending',
            'district_id'    => $district->id,
            'department_id'  => $department->id,
        ]);

        // ApprovalRequest も生成されている
        $rc = RoleChange::where('user_id', $target->id)->latest()->first();
        $this->assertNotNull($rc->approvalRequest);
        $this->assertSame('pending', $rc->approvalRequest->current_state);

        // 承認前は users / ロールが変わっていない
        $fresh = $target->fresh();
        $this->assertSame($originalRole, $fresh->getRoleNames()->first());
    }

    /** 14. admin 正常申請 → RoleChange に scopes 保存・users とロールは変わらない */
    public function test_admin_apply_persists_scopes_without_changing_user(): void
    {
        [$actor, $target, $district, $department] = $this->setUpActorAndTarget();
        $originalRole        = $target->getRoleNames()->first();
        $originalDistrictId  = $target->district_id;
        $originalDepartmentId = $target->department_id;

        $d2 = District::factory()->create();

        $this->actingAs($actor)
            ->postJson(route('roleChange.apply', $target), [
                'role'          => 'admin',
                'reason'        => '管理者に昇格',
                'district_id'   => $district->id,
                'department_id' => $department->id,
                'scopes'        => [
                    ['district_id' => $district->id, 'department_id' => $department->id],
                    ['district_id' => $d2->id,       'department_id' => $department->id],
                ],
            ])
            ->assertRedirect();

        $rc = RoleChange::where('user_id', $target->id)->latest()->first();

        // scopes が JSON で保存されている
        $this->assertCount(2, $rc->scopes);
        $this->assertSame($district->id, $rc->scopes[0]['district_id']);
        $this->assertSame($d2->id,       $rc->scopes[1]['district_id']);

        // 承認前は users / ロール / スコープテーブルが変わっていない
        $fresh = $target->fresh();
        $this->assertSame($originalRole,         $fresh->getRoleNames()->first());
        $this->assertSame($originalDistrictId,   $fresh->district_id);
        $this->assertSame($originalDepartmentId, $fresh->department_id);
        $this->assertDatabaseMissing('user_management_scopes', ['user_id' => $target->id]);
    }
}
