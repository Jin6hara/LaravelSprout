<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManageControllerTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_guest_is_redirected_from_page_and_unauthorized_from_api(): void
    {
        $this->get(route('data.role_manage'))
            ->assertRedirect(route('login'));

        $this->getJson(route('api.role_manage.snapshot'))
            ->assertStatus(401);
    }

    public function test_non_super_admin_users_cannot_access_role_manage(): void
    {
        $this->actingAs($this->makeGeneral())
            ->get(route('data.role_manage'))
            ->assertRedirect(route('welcome'));

        $this->actingAs($this->makeAdmin())
            ->getJson(route('api.role_manage.snapshot'))
            ->assertRedirect(route('welcome'));
    }

    public function test_data_list_link_is_only_visible_to_super_admin(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get(route('data.list'))
            ->assertOk()
            ->assertSee(route('data.role_manage'));

        $this->actingAs($this->makeAdmin())
            ->get(route('data.list'))
            ->assertOk()
            ->assertDontSee(route('data.role_manage'));
    }

    public function test_super_admin_can_view_snapshot_with_all_five_sections(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'feature.test', 'guard_name' => 'web']);
        Role::findByName('admin', 'web')->givePermissionTo($permission);
        $target = $this->makeGeneral();
        $target->givePermissionTo($permission);

        $this->actingAs($this->makeSuperAdmin())
            ->getJson(route('api.role_manage.snapshot'))
            ->assertOk()
            ->assertJsonStructure([
                'roles',
                'permissions',
                'users',
                'role_permissions',
                'model_roles',
                'model_permissions',
            ])
            ->assertJsonFragment(['name' => 'feature.test'])
            ->assertJsonFragment(['description' => 'コード側で定義された権限です。利用前にmiddleware / policy / view条件を確認してください。'])
            ->assertJsonFragment(['permission_name' => 'feature.test']);
    }

    public function test_mutations_require_confirmation(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->postJson(route('api.role_manage.roles.store'), ['name' => 'temporary'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirm']);
    }

    public function test_super_admin_can_create_update_and_delete_unused_role(): void
    {
        $actor = $this->makeSuperAdmin();

        $response = $this->actingAs($actor)
            ->postJson(route('api.role_manage.roles.store'), ['name' => 'temporary', 'confirm' => true])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'temporary']);

        $roleId = $response->json('id');
        $role = Role::findOrFail($roleId);

        $this->actingAs($actor)
            ->putJson(route('api.role_manage.roles.update', $role), ['name' => 'temporary_updated', 'confirm' => true])
            ->assertOk()
            ->assertJsonFragment(['name' => 'temporary_updated']);

        $role = $role->fresh();
        $this->assertSame('temporary_updated', $role->name);

        $this->actingAs($actor)
            ->deleteJson(route('api.role_manage.roles.destroy', $role), ['confirm' => true])
            ->assertNoContent();

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    public function test_core_and_used_roles_cannot_be_updated_or_deleted(): void
    {
        $actor = $this->makeSuperAdmin();
        $admin = Role::findByName('admin', 'web');

        $this->actingAs($actor)
            ->putJson(route('api.role_manage.roles.update', $admin), ['name' => 'admin2', 'confirm' => true])
            ->assertUnprocessable();

        $this->actingAs($actor)
            ->deleteJson(route('api.role_manage.roles.destroy', $admin), ['confirm' => true])
            ->assertUnprocessable();

        $used = Role::create(['name' => 'used_role', 'guard_name' => 'web']);
        $this->makeGeneral()->assignRole($used);

        $this->actingAs($actor)
            ->putJson(route('api.role_manage.roles.update', $used), ['name' => 'used_role2', 'confirm' => true])
            ->assertUnprocessable();

        $this->actingAs($actor)
            ->deleteJson(route('api.role_manage.roles.destroy', $used), ['confirm' => true])
            ->assertUnprocessable();
    }

    public function test_code_permissions_include_descriptions(): void
    {
        Permission::firstOrCreate(['name' => 'schedule.viewAll', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'teacher.viewAll', 'guard_name' => 'web']);

        $this->actingAs($this->makeSuperAdmin())
            ->getJson(route('api.role_manage.snapshot'))
            ->assertOk()
            ->assertJsonFragment([
                'name' => 'schedule.viewAll',
                'description' => '自分以外のScheduleの観覧権限',
            ])
            ->assertJsonFragment([
                'name' => 'teacher.viewAll',
                'description' => 'Master List の観覧権限',
            ]);
    }

    public function test_super_admin_can_add_update_and_delete_role_permission_relation(): void
    {
        $actor = $this->makeSuperAdmin();
        $role = Role::create(['name' => 'relation_role', 'guard_name' => 'web']);
        $first = Permission::create(['name' => 'relation.first', 'guard_name' => 'web']);
        $second = Permission::create(['name' => 'relation.second', 'guard_name' => 'web']);

        $this->actingAs($actor)
            ->postJson(route('api.role_manage.role_permissions.store'), [
                'role_id' => $role->id,
                'permission_id' => $first->id,
                'confirm' => true,
            ])
            ->assertCreated();

        $this->assertTrue($role->fresh()->hasPermissionTo($first));

        $this->actingAs($actor)
            ->putJson(route('api.role_manage.role_permissions.update', [$role, $first]), [
                'permission_id' => $second->id,
                'confirm' => true,
            ])
            ->assertOk();

        $role = $role->fresh();
        $this->assertFalse($role->hasPermissionTo($first));
        $this->assertTrue($role->hasPermissionTo($second));

        $this->actingAs($actor)
            ->deleteJson(route('api.role_manage.role_permissions.destroy', [$role, $second]), ['confirm' => true])
            ->assertNoContent();

        $this->assertFalse($role->fresh()->hasPermissionTo($second));
    }

    public function test_super_admin_can_add_update_and_delete_user_role_relation(): void
    {
        $actor = $this->makeSuperAdmin();
        $target = $this->makeGeneral();
        $first = Role::create(['name' => 'user_role_first', 'guard_name' => 'web']);
        $second = Role::create(['name' => 'user_role_second', 'guard_name' => 'web']);

        $this->actingAs($actor)
            ->postJson(route('api.role_manage.model_roles.store'), [
                'user_id' => $target->id,
                'role_id' => $first->id,
                'confirm' => true,
            ])
            ->assertCreated();

        $this->assertTrue($target->fresh()->hasRole($first));

        $this->actingAs($actor)
            ->putJson(route('api.role_manage.model_roles.update', [$target->id, $first]), [
                'role_id' => $second->id,
                'confirm' => true,
            ])
            ->assertOk();

        $target = $target->fresh();
        $this->assertFalse($target->hasRole($first));
        $this->assertTrue($target->hasRole($second));

        $this->actingAs($actor)
            ->deleteJson(route('api.role_manage.model_roles.destroy', [$target->id, $second]), ['confirm' => true])
            ->assertNoContent();

        $this->assertFalse($target->fresh()->hasRole($second));
    }

    public function test_super_admin_cannot_remove_super_admin_from_self(): void
    {
        $actor = $this->makeSuperAdmin();
        $role = Role::findByName('super_admin', 'web');

        $this->actingAs($actor)
            ->deleteJson(route('api.role_manage.model_roles.destroy', [$actor->id, $role]), ['confirm' => true])
            ->assertUnprocessable();

        $this->assertTrue($actor->fresh()->hasRole('super_admin'));
    }

    public function test_super_admin_can_add_update_and_delete_direct_user_permission_relation(): void
    {
        $actor = $this->makeSuperAdmin();
        $target = $this->makeGeneral();
        $first = Permission::create(['name' => 'direct.first', 'guard_name' => 'web']);
        $second = Permission::create(['name' => 'direct.second', 'guard_name' => 'web']);

        $this->actingAs($actor)
            ->postJson(route('api.role_manage.model_permissions.store'), [
                'user_id' => $target->id,
                'permission_id' => $first->id,
                'confirm' => true,
            ])
            ->assertCreated();

        $this->assertTrue($target->fresh()->hasDirectPermission($first));

        $this->actingAs($actor)
            ->putJson(route('api.role_manage.model_permissions.update', [$target->id, $first]), [
                'permission_id' => $second->id,
                'confirm' => true,
            ])
            ->assertOk();

        $target = $target->fresh();
        $this->assertFalse($target->hasDirectPermission($first));
        $this->assertTrue($target->hasDirectPermission($second));

        $this->actingAs($actor)
            ->deleteJson(route('api.role_manage.model_permissions.destroy', [$target->id, $second]), ['confirm' => true])
            ->assertNoContent();

        $this->assertFalse($target->fresh()->hasDirectPermission($second));
    }
}
