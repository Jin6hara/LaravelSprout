<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = ['lesson_name', 'lesson_code', 'note', 'lesson_minute', 'lesson_type', 'ps_unique_lesson_code', 'fm_lesson_code'];

    public function scheduleDetails(): HasMany
    {
        return $this->hasMany(ScheduleDetail::class);
    }
}
