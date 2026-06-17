<?php

/**
 * 部署情報のCRUD操作に対するアクセス制御を定義するポリシー。
 */
namespace App\Policies;

use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Department $department): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, Department $department): bool
    {
        return $user->isSuperAdmin();
    }
}
