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
        // 既存ロールを置換するなら syncRoles、追加なら assignRole
        $user->syncRoles([$this->requested_role]);
    }
}
