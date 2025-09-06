<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleDetail extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = ['schedule_line_id', 'lesson_start_time_id', 'lesson_id'];

    public function scheduleLine(): BelongsTo
    {
        return $this->belongsTo(ScheduleLine::class);
    }

    public function start(): BelongsTo
    {
        return $this->belongsTo(LessonStartTime::class, 'lesson_start_time_id');
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
