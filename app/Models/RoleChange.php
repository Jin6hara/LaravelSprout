<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'requested_role',
        'reason',
        'requested_by_id',
        'status',
        'district_id',
        'department_id',
        'scopes',
    ];

    protected $casts = [
        'scopes' => 'array',
    ];

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }

    /**
     * ドメイン反映メソッド
     * 例: 実ロール変更を行う
     *
     * @return void
     */
    public function applyDomainEffect(): void
    {
        $user = $this->targetUser;

        $user->syncRoles([$this->requested_role]);

        $user->district_id   = $this->district_id;
        $user->department_id = $this->department_id;
        $user->save();

        if (in_array($this->requested_role, ['admin', 'super_admin'])) {
            $user->managementScopes()->delete();
            foreach ($this->scopes ?? [] as $scope) {
                $user->managementScopes()->firstOrCreate([
                    'district_id'   => $scope['district_id'],
                    'department_id' => $scope['department_id'],
                ]);
            }
        } else {
            $user->managementScopes()->delete();
        }
    }
}
