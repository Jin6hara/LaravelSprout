<?php

namespace App\Policies;

use App\Models\ScheduleLine;
use App\Models\User;
use App\Services\CurrentScopeService;

class ScheduleLinePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ScheduleLine $line): bool
    {
        return $this->canManage($user, $line);
    }

    public function update(User $user, ScheduleLine $line): bool
    {
        return $this->canManage($user, $line);
    }

    public function delete(User $user, ScheduleLine $line): bool
    {
        return $this->canManage($user, $line);
    }

    public function copy(User $user, ScheduleLine $line): bool
    {
        return $this->canManage($user, $line);
    }

    private function canManage(User $user, ScheduleLine $line): bool
    {
        $scope = app(CurrentScopeService::class);

        return $user->isAdmin()
            && $line->district_id === $scope->currentDistrictId();
    }
}
