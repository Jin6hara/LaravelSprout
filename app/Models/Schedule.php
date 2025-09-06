<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = ['label', 'total_minutes', 'effective_start', 'effective_end', 'is_active', 'user_id', 'name'];
    protected $casts = ['effective_start' => 'date', 'effective_end' => 'date', 'is_active' => 'boolean'];

    public function lines()
    {
        return $this->hasMany(ScheduleLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeBetween($q, $s, $e)
    {
        return $q->where('is_active', true)
            ->whereDate('effective_end', '>=', $s)
            ->whereDate('effective_start', '<=', $e);
    }

    public function assignments()
    {
        return $this->hasMany(UserScheduleAssignment::class);
    }
}
