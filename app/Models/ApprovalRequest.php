<?php

/**
 * ロール変更・有給申請などの承認依頼を管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'approvable_type',
        'approvable_id',
        'title',
        'requested_by_id',
        'current_state',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Role|Paid Leave|Overtime
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    // Role|Paid Leave|Overtime
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }

    public function approvalDistrictId(): ?int
    {
        $approvable = $this->approvable;

        return $approvable?->district_id
            ?? ($this->metadata['district_id'] ?? null)
            ?? ($this->metadata['requested_district_id'] ?? null);
    }

    public function approvalDepartmentId(): ?int
    {
        $approvable = $this->approvable;

        return $approvable?->department_id
            ?? ($this->metadata['department_id'] ?? null)
            ?? ($this->metadata['requested_department_id'] ?? null);
    }
}
