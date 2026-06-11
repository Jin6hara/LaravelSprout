<?php

/**
 * 休日パターンの特定日付に対する例外調整（振替・臨時休業など）を管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class RestPatternAdjustment extends Model
{
    use HasFactory;

    protected $fillable = ['rest_pattern_id', 'date', 'kind', 'code', 'title', 'is_active', 'note'];
    protected $casts = ['date' => 'date', 'is_active' => 'boolean'];

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(RestPattern::class, 'rest_pattern_id');
    }

    public function scopeBetween(Builder $q, $start, $end): Builder
    {
        return $q->where('is_active', true)
            ->whereDate('date', '>=', $start)
            ->whereDate('date', '<=', $end);
    }
}
