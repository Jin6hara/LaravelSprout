<?php

/**
 * ロール・パーミッション・ユーザーのロール付与状況を一覧取得するスナップショットサービス。
 */
namespace App\Services\RoleManage;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class RoleManageSnapshotService
{
    private const GUARD = 'web';

    public function rolePermissions(): Collection
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

    public function modelRoles(): Collection
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

    public function modelPermissions(): Collection
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

    public function directUserPermissionCount(Permission $permission): int
    {
        return DB::table(config('permission.table_names.model_has_permissions'))
            ->where('permission_id', $permission->id)
            ->where('model_type', User::class)
            ->count();
    }
}
