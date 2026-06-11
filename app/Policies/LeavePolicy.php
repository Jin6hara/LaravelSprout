<?php

/**
 * 欠勤・休暇レコードの閲覧・編集・管理に対するアクセス制御を定義するポリシー。
 */
namespace App\Policies;

use App\Models\Leave;
use App\Models\User;
use App\Services\CurrentScopeService;

class LeavePolicy
{
    public function view(User $user, Leave $leave): bool
    {
        return $user->id === $leave->user_id
            || $this->canManageScopedLeave($user, $leave);
    }

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function cancel(User $user, Leave $leave): bool
    {
        return $user->id === $leave->user_id
            || $this->canManageScopedLeave($user, $leave);
    }

    public function report(User $user, Leave $leave): bool
    {
        return $user->id === $leave->user_id
            || $this->canManageScopedLeave($user, $leave);
    }

    public function download(User $user, Leave $leave): bool
    {
        return $user->id === $leave->user_id
            || $this->canManageScopedLeave($user, $leave);
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Leave $leave): bool
    {
        return $this->canManageScopedLeave($user, $leave);
    }

    public function delete(User $user, Leave $leave): bool
    {
        return $this->canManageScopedLeave($user, $leave);
    }

    private function canManageScopedLeave(User $actor, Leave $leave): bool
    {
        if (! $actor->isAdmin()) {
            return false;
        }

        if ($leave->user_id !== null) {
            return in_array($leave->user_id, app(CurrentScopeService::class)->targetUserIds(), true);
        }

        $scope = app(CurrentScopeService::class);

        return $leave->district_id === $scope->currentDistrictId()
            && $leave->department_id === $scope->currentDepartmentId();
    }
}
