<?php

/**
 * 通勤経路パターンの管理・更新・削除に対するアクセス制御を定義するポリシー。
 */
namespace App\Policies;

use App\Models\CommutePattern;
use App\Models\User;

class CommutePatternPolicy
{
    public function manage(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, CommutePattern $pattern): bool
    {
        return $actor->hasAnyRole(['admin', 'super_admin'])
            && $actor->can('update', $pattern->user);
    }

    public function delete(User $actor, CommutePattern $pattern): bool
    {
        return $this->update($actor, $pattern);
    }
}
