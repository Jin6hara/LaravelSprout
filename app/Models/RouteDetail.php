<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use App\Enums\ExpenseTripType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteDetail extends Model
{
    protected $fillable = [
        'route_declaration_id',
        'dow',
        'from_station',
        'to_station',
        'trip_type',
        'amount',
        'route_text',
        'note',
    ];

    protected $casts = [
        // Enumを使う場合（Laravel 9+）
        'dow'       => DayOfWeek::class,
        'trip_type' => ExpenseTripType::class,
    ];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(RouteDeclaration::class, 'route_declaration_id');
    }
}
