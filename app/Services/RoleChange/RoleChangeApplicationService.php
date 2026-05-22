<?php

namespace App\Services\RoleChange;

use App\Models\RoleChange;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleChangeApplicationService
{
    public function apply(User $targetUser, User $requester, string $role, string $reason): void
    {
        DB::transaction(function () use ($targetUser, $requester, $role, $reason) {
            $rc = RoleChange::create([
                'user_id'         => $targetUser->id,
                'requested_role'  => $role,
                'reason'          => $reason,
                'requested_by_id' => $requester->id,
                'status'          => 'pending',
            ]);

            $roleLabels = ['general' => '一般', 'admin' => '管理者', 'super_admin' => 'スーパー管理者'];
            $title = '権限変更: ' . $rc->targetUser->role_label . ' → ' . ($roleLabels[$role] ?? $role);

            $rc->approvalRequest()->create([
                'title'           => $title,
                'requested_by_id' => $requester->id,
                'current_state'   => 'pending',
                'metadata'        => [
                    'target_user_id'   => $targetUser->id,
                    'target_user_name' => $targetUser->name,
                    'requested_role'   => $role,
                    'reason'           => $reason,
                    'flow'             => ['type' => 'any_of_role', 'role' => 'super_admin', 'min_approvals' => 1],
                    'current_role'     => $targetUser->getRoleNames()->first(),
                ],
            ]);

            $approvers = Role::findByName('super_admin')->users()->get();
            foreach ($approvers as $approver) {
                $approver->notify(new ApprovalRequestedNotification($rc->approvalRequest));
            }
        });
    }
}
