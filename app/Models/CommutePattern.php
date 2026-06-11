<?php

/**
 * ユーザーの通勤経路パターンを管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommutePattern extends Model
{
    protected $fillable = [
        'user_id',
        'submitted_at',
        'closest_station',
        'train_line',
        'valid_from',
        'valid_to',
        'reason',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legs(): HasMany
    {
        return $this->hasMany(CommutePatternLeg::class);
    }
}
