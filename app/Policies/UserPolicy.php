<?php

namespace App\Policies;

use App\Models\User;
use App\Services\CurrentScopeService;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $currentUser, User $targetUser): bool
    {
        return $currentUser->is($targetUser)
            || $this->isScopedAdminFor($currentUser, $targetUser);
    }
    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $currentUser, User $targetUser): bool
    {
        return $currentUser->is($targetUser)
            || $this->isScopedAdminFor($currentUser, $targetUser);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        //
    }

    public function applyRoleChange(User $actor, User $target): bool
    {
        return $this->isScopedAdminFor($actor, $target);
    }

    public function manage(User $actor, User $target): bool
    {
        return $this->isScopedAdminFor($actor, $target);
    }

    private function isScopedAdminFor(User $actor, User $target): bool
    {
        return $actor->isAdmin()
            && in_array($target->id, app(CurrentScopeService::class)->targetUserIds(), true);
    }
}
