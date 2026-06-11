<?php

/**
 * 通勤経路パターンの各区間（乗車区間・費用）を管理するモデル。
 */
namespace App\Models;

use App\Enums\DayOfWeek;
use App\Enums\ExpenseTripType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommutePatternLeg extends Model
{
    protected $fillable = [
        'commute_pattern_id',
        'dow',
        'seq',
        'station_from',
        'station_to',
        'note',
        'cost',
        'trip_type',
    ];

    protected $casts = [
        'dow' => DayOfWeek::class,
        'trip_type' => ExpenseTripType::class,
        'cost' => 'integer',
        'seq' => 'integer',
    ];

    public function pattern(): BelongsTo
    {
        return $this->belongsTo(CommutePattern::class, 'commute_pattern_id');
    }
}
