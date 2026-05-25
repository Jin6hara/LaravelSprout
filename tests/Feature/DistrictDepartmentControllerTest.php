<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DistrictDepartmentController のテスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [ゲスト]
 *   1. ページへの未認証アクセスはログインへリダイレクト
 *   2. District API（GET）への未認証アクセスはログインへリダイレクト
 *   3. Department API（GET）への未認証アクセスはログインへリダイレクト
 *
 * [権限: general ユーザー]
 *   4. ページは welcome へリダイレクト（role ミドルウェアが UnauthorizedException → Handler が welcome へ誘導）
 *   5. District API（GET）は welcome へリダイレクト
 *   6. Department API（GET）は welcome へリダイレクト
 *
 * [権限: admin ユーザー（super_admin ではない）]
 *   7. ページは welcome へリダイレクト
 *   8. District API（GET）は welcome へリダイレクト
 *   9. District API（POST）は welcome へリダイレクト
 *  10. District API（PUT）は welcome へリダイレクト
 *  11. District API（DELETE）は welcome へリダイレクト
 *  12. Department API（GET）は welcome へリダイレクト
 *  13. Department API（POST）は welcome へリダイレクト
 *  14. Department API（PUT）は welcome へリダイレクト
 *  15. Department API（DELETE）は welcome へリダイレクト
 *
 * [権限: super_admin（正常系）]
 *  16. ページを表示できる（200）
 *  17. District 一覧を取得できる（JSON）
 *  18. District を作成できる（201 + DB確認）
 *  19. District を更新できる（200 + DB確認）
 *  20. District を削除できる（204 + DB確認、子は parent_id が null に）
 *  21. District を親付きで作成できる
 *  22. Department 一覧を取得できる（JSON）
 *  23. Department を作成できる（201 + DB確認）
 *  24. Department を更新できる（200 + DB確認）
 *  25. Department を削除できる（204 + DB確認）
 *
 * [バリデーション]
 *  26. District 作成時 name が空だと 422
 *  27. District 更新時 name が空だと 422
 *  28. Department 作成時 name が空だと 422
 *
 * [data_list ページ]
 *  29. super_admin は District & Department リンクが表示される
 *  30. admin はリンクが表示されない
 * ─────────────────────────────────────────────────────────────────────────────
 */
class DistrictDepartmentControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // ヘルパー
    // =========================================================================

    private function makeSuperAdmin(): User
    {
        return User::factory()->superAdmin()->create();
    }

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makeGeneral(): User
    {
        return User::factory()->general()->create();
    }

    // =========================================================================
    // ゲスト
    // =========================================================================

    public function test_guest_redirected_from_page(): void
    {
        $this->get(route('data.district_department'))
            ->assertRedirect(route('login'));
    }

    public function test_guest_redirected_from_district_api(): void
    {
        $this->getJson(route('api.district.index'))
            ->assertStatus(401);
    }

    public function test_guest_redirected_from_department_api(): void
    {
        $this->getJson(route('api.department.index'))
            ->assertStatus(401);
    }

    // =========================================================================
    // general ユーザー
    // =========================================================================

    public function test_general_cannot_view_page(): void
    {
        $this->actingAs($this->makeGeneral())
            ->get(route('data.district_department'))
            ->assertRedirect(route('welcome'));
    }

    public function test_general_cannot_access_district_api(): void
    {
        $this->actingAs($this->makeGeneral())
            ->getJson(route('api.district.index'))
            ->assertRedirect(route('welcome'));
    }

    public function test_general_cannot_access_department_api(): void
    {
        $this->actingAs($this->makeGeneral())
            ->getJson(route('api.department.index'))
            ->assertRedirect(route('welcome'));
    }

    // =========================================================================
    // admin ユーザー（super_admin ではない）
    // =========================================================================

    public function test_admin_cannot_view_page(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('data.district_department'))
            ->assertRedirect(route('welcome'));
    }

    public function test_admin_cannot_list_districts(): void
    {
        $this->actingAs($this->makeAdmin())
            ->getJson(route('api.district.index'))
            ->assertRedirect(route('welcome'));
    }

    public function test_admin_cannot_create_district(): void
    {
        $this->actingAs($this->makeAdmin())
            ->postJson(route('api.district.store'), ['name' => 'Test'])
            ->assertRedirect(route('welcome'));
    }

    public function test_admin_cannot_update_district(): void
    {
        $district = District::factory()->create();

        $this->actingAs($this->makeAdmin())
            ->putJson(route('api.district.update', $district), ['name' => 'New'])
            ->assertRedirect(route('welcome'));
    }

    public function test_admin_cannot_delete_district(): void
    {
        $district = District::factory()->create();

        $this->actingAs($this->makeAdmin())
            ->deleteJson(route('api.district.destroy', $district))
            ->assertRedirect(route('welcome'));
    }

    public function test_admin_cannot_list_departments(): void
    {
        $this->actingAs($this->makeAdmin())
            ->getJson(route('api.department.index'))
            ->assertRedirect(route('welcome'));
    }

    public function test_admin_cannot_create_department(): void
    {
        $this->actingAs($this->makeAdmin())
            ->postJson(route('api.department.store'), ['name' => 'Test'])
            ->assertRedirect(route('welcome'));
    }

    public function test_admin_cannot_update_department(): void
    {
        $dept = Department::factory()->create();

        $this->actingAs($this->makeAdmin())
            ->putJson(route('api.department.update', $dept), ['name' => 'New'])
            ->assertRedirect(route('welcome'));
    }

    public function test_admin_cannot_delete_department(): void
    {
        $dept = Department::factory()->create();

        $this->actingAs($this->makeAdmin())
            ->deleteJson(route('api.department.destroy', $dept))
            ->assertRedirect(route('welcome'));
    }

    // =========================================================================
    // super_admin（正常系）
    // =========================================================================

    public function test_super_admin_can_view_page(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('data.district_department'))
            ->assertOk();
    }

    public function test_super_admin_can_list_districts(): void
    {
        District::factory()->count(3)->create();

        $this->actingAs($this->makeSuperAdmin())
            ->getJson(route('api.district.index'))
            ->assertOk()
            ->assertJsonStructure([['id', 'name', 'parent_id', 'children']]);
    }

    public function test_super_admin_can_create_district(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->postJson(route('api.district.store'), ['name' => 'New District'])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'New District']);

        $this->assertDatabaseHas('districts', ['name' => 'New District']);
    }

    public function test_super_admin_can_create_district_with_parent(): void
    {
        $parent = District::factory()->create(['name' => 'Parent']);

        $this->actingAs($this->makeSuperAdmin())
            ->postJson(route('api.district.store'), ['name' => 'Child', 'parent_id' => $parent->id])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->assertDatabaseHas('districts', ['name' => 'Child', 'parent_id' => $parent->id]);
    }

    public function test_super_admin_can_update_district(): void
    {
        $district = District::factory()->create(['name' => 'Old']);

        $this->actingAs($this->makeSuperAdmin())
            ->putJson(route('api.district.update', $district), ['name' => 'Updated', 'parent_id' => null])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated']);

        $this->assertDatabaseHas('districts', ['id' => $district->id, 'name' => 'Updated']);
    }

    public function test_super_admin_can_delete_district_and_children_become_top_level(): void
    {
        $parent = District::factory()->create(['name' => 'Parent']);
        $child  = District::factory()->create(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->actingAs($this->makeSuperAdmin())
            ->deleteJson(route('api.district.destroy', $parent))
            ->assertNoContent();

        $this->assertDatabaseMissing('districts', ['id' => $parent->id]);
        $this->assertDatabaseHas('districts', ['id' => $child->id, 'parent_id' => null]);
    }

    public function test_super_admin_can_list_departments(): void
    {
        Department::factory()->count(3)->create();

        $this->actingAs($this->makeSuperAdmin())
            ->getJson(route('api.department.index'))
            ->assertOk()
            ->assertJsonStructure([['id', 'name']]);
    }

    public function test_super_admin_can_create_department(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->postJson(route('api.department.store'), ['name' => 'New Dept'])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'New Dept']);

        $this->assertDatabaseHas('departments', ['name' => 'New Dept']);
    }

    public function test_super_admin_can_update_department(): void
    {
        $dept = Department::factory()->create(['name' => 'Old']);

        $this->actingAs($this->makeSuperAdmin())
            ->putJson(route('api.department.update', $dept), ['name' => 'Updated'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated']);

        $this->assertDatabaseHas('departments', ['id' => $dept->id, 'name' => 'Updated']);
    }

    public function test_super_admin_can_delete_department(): void
    {
        $dept = Department::factory()->create();

        $this->actingAs($this->makeSuperAdmin())
            ->deleteJson(route('api.department.destroy', $dept))
            ->assertNoContent();

        $this->assertDatabaseMissing('departments', ['id' => $dept->id]);
    }

    // =========================================================================
    // バリデーション
    // =========================================================================

    public function test_district_store_requires_name(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->postJson(route('api.district.store'), ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_district_update_requires_name(): void
    {
        $district = District::factory()->create();

        $this->actingAs($this->makeSuperAdmin())
            ->putJson(route('api.district.update', $district), ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_department_store_requires_name(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->postJson(route('api.department.store'), ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // =========================================================================
    // data_list ページの表示制御
    // =========================================================================

    public function test_super_admin_sees_district_department_link_on_data_list(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('data.list'))
            ->assertOk()
            ->assertSee(route('data.district_department'));
    }

    public function test_admin_does_not_see_district_department_link_on_data_list(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('data.list'))
            ->assertOk()
            ->assertDontSee(route('data.district_department'));
    }
}
