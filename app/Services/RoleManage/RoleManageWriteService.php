<?php

/**
 * ロール・パーミッションの付け替えおよびユーザーへのロール変更を担うサービス。
 */
namespace App\Services\RoleManage;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleManageWriteService
{
    public function swapRolePermission(Role $role, Permission $old, Permission $new): void
    {
        DB::transaction(function () use ($role, $old, $new) {
            $role->revokePermissionTo($old);
            $role->givePermissionTo($new);
        });
    }

    public function swapModelRole(User $user, Role $old, Role $new): void
    {
        DB::transaction(function () use ($user, $old, $new) {
            $user->removeRole($old);
            $user->assignRole($new);
        });
    }

    public function swapModelPermission(User $user, Permission $old, Permission $new): void
    {
        DB::transaction(function () use ($user, $old, $new) {
            $user->revokePermissionTo($old);
            $user->givePermissionTo($new);
        });
    }
}
