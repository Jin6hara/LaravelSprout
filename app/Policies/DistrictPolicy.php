<?php

namespace App\Policies;

use App\Models\District;
use App\Models\User;

class DistrictPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, District $district): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user, District $district): bool
    {
        return $user->isSuperAdmin();
    }
}
