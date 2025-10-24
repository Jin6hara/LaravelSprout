<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\TimeString;

class ScheduleDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'schedule_line_id',
        'lesson_start_time_id',
        'lesson_id',
        'effective_start',
        'effective_end',
    ];

    protected $casts = [
        'effective_start' => 'date',
        'effective_end'   => 'date',
    ];

    public function getStartHmAttribute(): string
    {
        return TimeString::normalizeToHm(optional($this->start)->start_time);
    }

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

    /**
     * 指定日が有効（effective_start <= day <= effective_end or end is null）
     */
    public function scopeActiveOn(Builder $q, Carbon|string $day): Builder
    {
        $d = $day instanceof Carbon ? $day->toDateString() : (string) $day;
        return $q->whereDate('effective_start', '<=', $d)
            ->where(function ($qq) use ($d) {
                $qq->whereNull('effective_end')
                    ->orWhereDate('effective_end', '>=', $d);
            });
    }

    /**
     * 期間がウィンドウ（[start, end]）と交差
     * （= start <= windowEnd && (end is null || end >= windowStart)）
     */
    public function scopeActiveBetween(Builder $q, Carbon $start, Carbon $end): Builder
    {
        $s = $start->toDateString();
        $e = $end->toDateString();
        return $q->whereDate('effective_start', '<=', $e)
            ->where(function ($qq) use ($s) {
                $qq->whereNull('effective_end')
                    ->orWhereDate('effective_end', '>=', $s);
            });
    }
}
