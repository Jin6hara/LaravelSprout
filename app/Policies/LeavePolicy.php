<?php

namespace App\Policies;

use App\Models\Leave;
use App\Models\User;

class LeavePolicy
{
    public function view(User $user, Leave $leave): bool
    {
        return $user->id === $leave->user_id
            || $user->isAdmin();
    }
}
