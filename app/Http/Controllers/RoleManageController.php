<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleManageController extends Controller
{
    private const GUARD = 'web';

    private const CORE_ROLES = [
        'super_admin',
        'admin',
    ];

    private const CODE_PERMISSIONS = [
        'schedule.viewAll',
        'teacher.viewAll',
    ];

    public function index()
    {
        $this->authorizeSuperAdmin();

        return view('data.role_manage');
    }

    public function snapshot()
    {
        $this->authorizeSuperAdmin();

        return response()->json([
            'roles' => Role::query()
                ->withCount(['permissions', 'users'])
                ->where('guard_name', self::GUARD)
                ->orderBy('name')
                ->get()
                ->map(fn (Role $role) => $this->formatRole($role))
                ->values(),
            'permissions' => Permission::query()
                ->withCount('roles')
                ->where('guard_name', self::GUARD)
                ->orderBy('name')
                ->get()
                ->map(fn (Permission $permission) => $this->formatPermission($permission))
                ->values(),
            'users' => User::query()
                ->orderBy('family_name')
                ->orderBy('first_name')
                ->get(['id', 'employee_code', 'family_name', 'first_name', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'employee_code' => $user->employee_code,
                    'family_name' => $user->family_name,
                    'first_name' => $user->first_name,
                    'name' => $user->name,
                    'email' => $user->email,
                ])
                ->values(),
            'role_permissions' => $this->rolePermissions(),
            'model_roles' => $this->modelRoles(),
            'model_permissions' => $this->modelPermissions(),
        ]);
    }

    public function storeRole(Request $request)
    {
        $this->authorizeSuperAdmin();
        $this->requireConfirmed($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/', Rule::unique('roles')->where('guard_name', self::GUARD)],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => self::GUARD]);
        $this->forgetPermissionCache();

        return response()->json($this->formatRole($role->loadCount(['permissions', 'users'])), 201);
    }

    public function updateRole(Request $request, Role $role)
    {
        $this->authorizeSuperAdmin();
        $this->requireWebGuard($role);
        $this->requireConfirmed($request);
        $this->ensureRoleCanBeChanged($role, 'updated');

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[A-Za-z0-9_.-]+$/',
                Rule::unique('roles')->where('guard_name', self::GUARD)->ignore($role->id),
            ],
        ]);

        $role->update(['name' => $data['name']]);
        $this->forgetPermissionCache();

        return response()->json($this->formatRole($role->fresh()->loadCount(['permissions', 'users'])));
    }

    public function destroyRole(Request $request, Role $role)
    {
        $this->authorizeSuperAdmin();
        $this->requireWebGuard($role);
        $this->requireConfirmed($request);
        $this->ensureRoleCanBeChanged($role, 'deleted');

        $role->delete();
        $this->forgetPermissionCache();

        return response()->json(null, 204);
    }

    public function storeRolePermission(Request $request)
    {
        $this->authorizeSuperAdmin();
        $this->requireConfirmed($request);

        [$role, $permission] = $this->validateRolePermissionInput($request);

        abort_if($role->hasPermissionTo($permission), 422, 'This role already has the selected permission.');

        $role->givePermissionTo($permission);
        $this->forgetPermissionCache();

        return response()->json(['message' => 'Role permission added.'], 201);
    }

    public function updateRolePermission(Request $request, Role $role, Permission $permission)
    {
        $this->authorizeSuperAdmin();
        $this->requireWebGuard($role);
        $this->requireWebGuard($permission);
        $this->requireConfirmed($request);

        $data = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $newPermission = Permission::findOrFail($data['permission_id']);
        $this->requireWebGuard($newPermission);
        $this->ensureRolePermissionCanBeRemoved($role, $permission);

        abort_unless($role->hasPermissionTo($permission), 422, 'The selected relation does not exist.');
        abort_if($role->hasPermissionTo($newPermission), 422, 'This role already has the target permission.');

        DB::transaction(function () use ($role, $permission, $newPermission) {
            $role->revokePermissionTo($permission);
            $role->givePermissionTo($newPermission);
        });
        $this->forgetPermissionCache();

        return response()->json(['message' => 'Role permission updated.']);
    }

    public function destroyRolePermission(Request $request, Role $role, Permission $permission)
    {
        $this->authorizeSuperAdmin();
        $this->requireWebGuard($role);
        $this->requireWebGuard($permission);
        $this->requireConfirmed($request);
        $this->ensureRolePermissionCanBeRemoved($role, $permission);

        abort_unless($role->hasPermissionTo($permission), 422, 'The selected relation does not exist.');

        $role->revokePermissionTo($permission);
        $this->forgetPermissionCache();

        return response()->json(null, 204);
    }

    public function storeModelRole(Request $request)
    {
        $this->authorizeSuperAdmin();
        $this->requireConfirmed($request);

        [$user, $role] = $this->validateUserRoleInput($request);

        abort_if($user->hasRole($role), 422, 'This user already has the selected role.');

        $user->assignRole($role);
        $this->forgetPermissionCache();

        return response()->json(['message' => 'User role added.'], 201);
    }

    public function updateModelRole(Request $request, User $user, Role $role)
    {
        $this->authorizeSuperAdmin();
        $this->requireWebGuard($role);
        $this->requireConfirmed($request);

        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $newRole = Role::findOrFail($data['role_id']);
        $this->requireWebGuard($newRole);
        $this->ensureRoleCanBeDetachedFromUser($user, $role);

        abort_unless($user->hasRole($role), 422, 'The selected relation does not exist.');
        abort_if($user->hasRole($newRole), 422, 'This user already has the target role.');

        DB::transaction(function () use ($user, $role, $newRole) {
            $user->removeRole($role);
            $user->assignRole($newRole);
        });
        $this->forgetPermissionCache();

        return response()->json(['message' => 'User role updated.']);
    }

    public function destroyModelRole(Request $request, User $user, Role $role)
    {
        $this->authorizeSuperAdmin();
        $this->requireWebGuard($role);
        $this->requireConfirmed($request);
        $this->ensureRoleCanBeDetachedFromUser($user, $role);

        abort_unless($user->hasRole($role), 422, 'The selected relation does not exist.');

        $user->removeRole($role);
        $this->forgetPermissionCache();

        return response()->json(null, 204);
    }

    public function storeModelPermission(Request $request)
    {
        $this->authorizeSuperAdmin();
        $this->requireConfirmed($request);

        [$user, $permission] = $this->validateUserPermissionInput($request);

        abort_if($user->hasDirectPermission($permission), 422, 'This user already has the selected direct permission.');

        $user->givePermissionTo($permission);
        $this->forgetPermissionCache();

        return response()->json(['message' => 'User direct permission added.'], 201);
    }

    public function updateModelPermission(Request $request, User $user, Permission $permission)
    {
        $this->authorizeSuperAdmin();
        $this->requireWebGuard($permission);
        $this->requireConfirmed($request);

        $data = $request->validate([
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $newPermission = Permission::findOrFail($data['permission_id']);
        $this->requireWebGuard($newPermission);

        abort_unless($user->hasDirectPermission($permission), 422, 'The selected relation does not exist.');
        abort_if($user->hasDirectPermission($newPermission), 422, 'This user already has the target direct permission.');

        DB::transaction(function () use ($user, $permission, $newPermission) {
            $user->revokePermissionTo($permission);
            $user->givePermissionTo($newPermission);
        });
        $this->forgetPermissionCache();

        return response()->json(['message' => 'User direct permission updated.']);
    }

    public function destroyModelPermission(Request $request, User $user, Permission $permission)
    {
        $this->authorizeSuperAdmin();
        $this->requireWebGuard($permission);
        $this->requireConfirmed($request);

        abort_unless($user->hasDirectPermission($permission), 422, 'The selected relation does not exist.');

        $user->revokePermissionTo($permission);
        $this->forgetPermissionCache();

        return response()->json(null, 204);
    }

    private function authorizeSuperAdmin(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
    }

    private function requireConfirmed(Request $request): void
    {
        $request->validate([
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Confirmation is required before changing role management data.',
        ]);
    }

    private function requireWebGuard(Role|Permission $model): void
    {
        abort_unless($model->guard_name === self::GUARD, 404);
    }

    private function validateRolePermissionInput(Request $request): array
    {
        $data = $request->validate([
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $role = Role::findOrFail($data['role_id']);
        $permission = Permission::findOrFail($data['permission_id']);
        $this->requireWebGuard($role);
        $this->requireWebGuard($permission);

        return [$role, $permission];
    }

    private function validateUserRoleInput(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $role = Role::findOrFail($data['role_id']);
        $this->requireWebGuard($role);

        return [$user, $role];
    }

    private function validateUserPermissionInput(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'permission_id' => ['required', 'integer', 'exists:permissions,id'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $permission = Permission::findOrFail($data['permission_id']);
        $this->requireWebGuard($permission);

        return [$user, $permission];
    }

    private function ensureRoleCanBeChanged(Role $role, string $action): void
    {
        abort_if(in_array($role->name, self::CORE_ROLES, true), 422, "Core role '{$role->name}' cannot be {$action}.");

        $role->loadCount(['permissions', 'users']);
        abort_if($role->users_count > 0, 422, "This role is assigned to users and cannot be {$action}.");
        abort_if($role->permissions_count > 0, 422, "This role has permissions and cannot be {$action}.");
    }

    private function ensureRolePermissionCanBeRemoved(Role $role, Permission $permission): void
    {
        abort_if(
            $role->name === 'super_admin' && in_array($permission->name, self::CODE_PERMISSIONS, true),
            422,
            'Code permissions cannot be removed from super_admin.'
        );
    }

    private function ensureRoleCanBeDetachedFromUser(User $user, Role $role): void
    {
        if ($role->name !== 'super_admin') {
            return;
        }

        abort_if($user->id === auth()->id(), 422, 'You cannot remove super_admin from your own account.');
        abort_if($role->users()->count() <= 1, 422, 'At least one super_admin user must remain.');
    }

    private function rolePermissions()
    {
        return DB::table(config('permission.table_names.role_has_permissions') . ' as rp')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->where('r.guard_name', self::GUARD)
            ->where('p.guard_name', self::GUARD)
            ->orderBy('r.name')
            ->orderBy('p.name')
            ->get([
                'r.id as role_id',
                'r.name as role_name',
                'p.id as permission_id',
                'p.name as permission_name',
            ]);
    }

    private function modelRoles()
    {
        return DB::table(config('permission.table_names.model_has_roles') . ' as mr')
            ->join('roles as r', 'r.id', '=', 'mr.role_id')
            ->join('users as u', 'u.id', '=', 'mr.model_id')
            ->where('mr.model_type', User::class)
            ->whereNull('u.deleted_at')
            ->where('r.guard_name', self::GUARD)
            ->orderBy('u.family_name')
            ->orderBy('u.first_name')
            ->orderBy('r.name')
            ->get([
                'u.id as user_id',
                'u.employee_code',
                'u.family_name',
                'u.first_name',
                'u.name as user_name',
                'u.email',
                'r.id as role_id',
                'r.name as role_name',
            ]);
    }

    private function modelPermissions()
    {
        return DB::table(config('permission.table_names.model_has_permissions') . ' as mp')
            ->join('permissions as p', 'p.id', '=', 'mp.permission_id')
            ->join('users as u', 'u.id', '=', 'mp.model_id')
            ->where('mp.model_type', User::class)
            ->whereNull('u.deleted_at')
            ->where('p.guard_name', self::GUARD)
            ->orderBy('u.family_name')
            ->orderBy('u.first_name')
            ->orderBy('p.name')
            ->get([
                'u.id as user_id',
                'u.employee_code',
                'u.name as user_name',
                'u.email',
                'p.id as permission_id',
                'p.name as permission_name',
            ]);
    }

    private function formatRole(Role $role): array
    {
        $permissionsCount = (int) ($role->permissions_count ?? $role->permissions()->count());
        $usersCount = (int) ($role->users_count ?? $role->users()->count());
        $isCore = in_array($role->name, self::CORE_ROLES, true);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions_count' => $permissionsCount,
            'users_count' => $usersCount,
            'is_core' => $isCore,
            'can_update' => ! $isCore && $permissionsCount === 0 && $usersCount === 0,
            'can_delete' => ! $isCore && $permissionsCount === 0 && $usersCount === 0,
        ];
    }

    private function formatPermission(Permission $permission): array
    {
        $rolesCount = (int) ($permission->roles_count ?? $permission->roles()->count());
        $usersCount = $this->directUserPermissionCount($permission);
        $isCodePermission = in_array($permission->name, self::CODE_PERMISSIONS, true);

        return [
            'id' => $permission->id,
            'name' => $permission->name,
            'guard_name' => $permission->guard_name,
            'roles_count' => $rolesCount,
            'users_count' => $usersCount,
            'is_code_permission' => $isCodePermission,
            'description' => $this->permissionDescription($permission->name),
            'can_update' => ! $isCodePermission && $rolesCount === 0 && $usersCount === 0,
            'can_delete' => ! $isCodePermission && $rolesCount === 0 && $usersCount === 0,
        ];
    }

    private function permissionDescription(string $name): string
    {
        return match ($name) {
            'schedule.viewAll' => '自分以外のScheduleの観覧権限',
            'teacher.viewAll' => 'Master List の観覧権限',
            default => 'コード側で定義された権限です。利用前にmiddleware / policy / view条件を確認してください。',
        };
    }

    private function directUserPermissionCount(Permission $permission): int
    {
        return DB::table(config('permission.table_names.model_has_permissions'))
            ->where('permission_id', $permission->id)
            ->where('model_type', User::class)
            ->count();
    }

    private function forgetPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
