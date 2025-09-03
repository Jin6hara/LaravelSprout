<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = ['label', 'total_minutes', 'effective_start', 'effective_end', 'is_active'];
    protected $casts = ['effective_start' => 'date', 'effective_end' => 'date', 'is_active' => 'boolean'];
    public function lines()
    {
        return $this->hasMany(ScheduleLine::class);
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
