<?php

/**
 * ユーザーの有給残日数（付与・使用）を管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveCredit extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'fy', 'granted_days', 'used_days'];

    public function transactions(): HasMany
    {
        return $this->hasMany(LeaveCreditTransaction::class);
    }

    // 残日数
    public function getRemainingDaysAttribute(): float
    {
        return max(0, (float)$this->granted_days - (float)$this->used_days);
    }
}
