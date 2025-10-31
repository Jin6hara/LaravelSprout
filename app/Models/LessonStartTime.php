<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonStartTime extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = ['start_time'];
    protected $casts = ['start_time' => 'datetime:H:i'];

    public function scheduleDetails(): HasMany
    {
        return $this->hasMany(ScheduleDetail::class);
    }
}
