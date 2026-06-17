<?php

/**
 * イベントに紐づくレッスン単位の詳細情報を管理するモデル。
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventDetail extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = [
        'event_id',
        'start_time',
        'lesson_id',
        'schedule_detail_id',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scheduleDetail(): BelongsTo
    {
        return $this->belongsTo(ScheduleDetail::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
