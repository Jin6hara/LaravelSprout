<?php

namespace App\Policies;

use App\Models\ScheduleDetail;
use App\Models\User;
use App\Services\CurrentScopeService;

class ScheduleDetailPolicy
{
    public function view(User $user, ScheduleDetail $detail): bool
    {
        return $this->canManage($user, $detail);
    }

    public function update(User $user, ScheduleDetail $detail): bool
    {
        return $this->canManage($user, $detail);
    }

    public function delete(User $user, ScheduleDetail $detail): bool
    {
        return $this->canManage($user, $detail);
    }

    public function copy(User $user, ScheduleDetail $detail): bool
    {
        return $this->canManage($user, $detail);
    }

    private function canManage(User $user, ScheduleDetail $detail): bool
    {
        $line = $detail->scheduleLine;
        $scope = app(CurrentScopeService::class);

        return $user->isAdmin()
            && $line !== null
            && $line->district_id === $scope->currentDistrictId();
    }
}
