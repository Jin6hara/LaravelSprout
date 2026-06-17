<?php

/**
 * 管理スコープの選択操作に対するアクセス制御を定義するポリシー。
 */
namespace App\Policies;

use App\Models\User;
use App\Models\UserManagementScope;

class UserManagementScopePolicy
{
    public function select(User $actor, UserManagementScope $scope): bool
    {
        return $actor->isAdmin() && $scope->user_id === $actor->id;
    }
}
