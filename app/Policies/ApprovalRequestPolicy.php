<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ApprovalRequest;

class ApprovalRequestPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function view(User $actor, ApprovalRequest $ar): bool
    {
        // 対象者本人 or 承認者（super_admin 等）
        if ($actor->id === ($ar->metadata['target_user_id'] ?? null)) return true;
        return $actor->hasRole('super_admin');
    }

    public function act(User $actor, ApprovalRequest $ar): bool
    {
        // 承認できるのは super_admin
        return $actor->hasRole('super_admin');
    }
}
