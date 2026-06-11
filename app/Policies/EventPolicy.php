<?php

/**
 * カレンダーイベントのCRUD操作に対するスコープ付きアクセス制御を定義するポリシー。
 */
namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Services\CurrentScopeService;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Event $event): bool
    {
        return $this->canManage($user, $event);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->canManage($user, $event);
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->canManage($user, $event);
    }

    private function canManage(User $user, Event $event): bool
    {
        $scope = app(CurrentScopeService::class);

        return $user->isAdmin()
            && $event->district_id === $scope->currentDistrictId()
            && $event->department_id === $scope->currentDepartmentId();
    }
}
