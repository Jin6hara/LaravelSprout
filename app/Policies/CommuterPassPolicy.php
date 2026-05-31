<?php

namespace App\Policies;

use App\Models\CommuterPass;
use App\Models\User;

class CommuterPassPolicy
{
    public function manage(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, CommuterPass $pass): bool
    {
        return $actor->hasAnyRole(['admin', 'super_admin'])
            && $actor->can('update', $pass->user);
    }

    public function delete(User $actor, CommuterPass $pass): bool
    {
        return $this->update($actor, $pass);
    }
}
