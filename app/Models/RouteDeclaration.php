<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RouteDeclaration extends Model
{
    protected $fillable = [
        'user_id',
        'submitted_at',
        'closest_station',
        'train_line',
        'effective_date',
        'reason',
        'note',
    ];

    protected $casts = [
        'submitted_at'  => 'datetime',
        'effective_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<RouteDetail> */
    public function details(): HasMany
    {
        return $this->hasMany(RouteDetail::class);
    }
}
