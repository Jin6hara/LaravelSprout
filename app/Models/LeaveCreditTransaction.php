<?php

/**
 * 有給残高の変動トランザクション（付与・消化・取消）を管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveCreditTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['leave_credit_id', 'type', 'days', 'reason'];
    public function credit(): BelongsTo
    {
        return $this->belongsTo(LeaveCredit::class, 'leave_credit_id');
    }
}
