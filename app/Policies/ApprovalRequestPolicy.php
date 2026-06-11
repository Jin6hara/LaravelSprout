<?php

namespace App\Policies;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\Notifications\ScopedNotificationService;

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
        if ($actor->id === ($ar->metadata['target_user_id'] ?? null)) {
            return true;
        }

        return $this->canManageApprovalScope($actor, $ar);
    }

    public function act(User $actor, ApprovalRequest $ar): bool
    {
        return $this->canManageApprovalScope($actor, $ar);
    }

    private function canManageApprovalScope(User $actor, ApprovalRequest $ar): bool
    {
        if (! $actor->hasRole('super_admin')) {
            return false;
        }

        return app(ScopedNotificationService::class)->matchesCurrentScope($ar);
    }
}
