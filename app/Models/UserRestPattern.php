<?php

/**
 * ユーザーへの休日パターン割り当て（有効期間付き）を管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class UserRestPattern extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'rest_pattern_id', 'start_date', 'end_date'];
    protected $casts = ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d']; // 形式を指定/フロント表示に関わる

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function pattern(): BelongsTo
    {
        return $this->belongsTo(RestPattern::class, 'rest_pattern_id');
    }

    // 指定期間に有効な割当を取得
    public function scopeActiveBetween(Builder $q, string|\Carbon\Carbon $start, string|\Carbon\Carbon $end): Builder
    {
        return $q->where(function ($qq) use ($start) {
            $qq->whereNull('end_date')->orWhere('end_date', '>=', $start);
        })->where('start_date', '<=', $end);
    }
}
