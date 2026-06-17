<?php

/**
 * 休日パターンの曜日別ルール（勤務日・休日の区分）を管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestPatternRule extends Model
{
    use HasFactory;

    protected $fillable = ['rest_pattern_id', 'weekday', 'kind'];
    public function pattern(): BelongsTo
    {
        return $this->belongsTo(RestPattern::class, 'rest_pattern_id');
    }
}
