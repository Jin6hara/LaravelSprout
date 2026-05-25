<?php

namespace App\Services\RoleChange;

use App\Models\Department;
use App\Models\District;
use App\Models\RoleChange;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleChangeApplicationService
{
    public function apply(
        User   $targetUser,
        User   $requester,
        string $role,
        string $reason,
        int    $districtId,
        int    $departmentId,
        array  $scopes = []
    ): void {
        DB::transaction(function () use ($targetUser, $requester, $role, $reason, $districtId, $departmentId, $scopes) {
            $rc = RoleChange::create([
                'user_id'         => $targetUser->id,
                'requested_role'  => $role,
                'reason'          => $reason,
                'requested_by_id' => $requester->id,
                'status'          => 'pending',
                'district_id'     => $districtId,
                'department_id'   => $departmentId,
                'scopes'          => $scopes,
            ]);

            $roleLabels = ['general' => '一般', 'admin' => '管理者', 'super_admin' => 'スーパー管理者'];
            $title = '権限・所属・管理範囲変更: ' . $rc->targetUser->role_label . ' → ' . ($roleLabels[$role] ?? $role);

            $targetUser->load(['district', 'department', 'managementScopes.district', 'managementScopes.department']);

            $requestedDistrict   = District::find($districtId);
            $requestedDepartment = Department::find($departmentId);

            $resolvedScopes = collect($scopes)->map(function ($s) {
                return [
                    'district_id'   => $s['district_id'],
                    'department_id' => $s['department_id'],
                    'district'      => District::find($s['district_id'])?->name,
                    'department'    => Department::find($s['department_id'])?->name,
                ];
            })->toArray();

            $rc->approvalRequest()->create([
                'title'           => $title,
                'requested_by_id' => $requester->id,
                'current_state'   => 'pending',
                'metadata'        => [
                    'target_user_id'       => $targetUser->id,
                    'target_user_name'     => $targetUser->name,
                    'requested_role'       => $role,
                    'reason'               => $reason,
                    'flow'                 => ['type' => 'any_of_role', 'role' => 'super_admin', 'min_approvals' => 1],
                    'current_role'         => $targetUser->getRoleNames()->first(),
                    'current_district'     => $targetUser->district?->name,
                    'current_department'   => $targetUser->department?->name,
                    'current_scopes'       => $targetUser->managementScopes->map(fn($s) => [
                        'district'   => $s->district?->name,
                        'department' => $s->department?->name,
                    ])->toArray(),
                    'requested_district'   => $requestedDistrict?->name,
                    'requested_department' => $requestedDepartment?->name,
                    'requested_scopes'     => $resolvedScopes,
                ],
            ]);

            $approvers = Role::findByName('super_admin')->users()->get();
            foreach ($approvers as $approver) {
                $approver->notify(new ApprovalRequestedNotification($rc->approvalRequest));
            }
        });
    }
}
