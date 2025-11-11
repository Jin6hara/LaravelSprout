<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ShiftType;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_date',
        'original_user_id',
        'Leave_type',
        'title',
        'school_name',
        'start_time',
        'end_time',
        'total_duration',
        'Lesson',
        'assigned_user_id',
        'status',
        'type',
        'notes',
        'source_schedule_line_id',
        'source_leave_id',
    ];

    protected $casts = [
        'event_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
        'type' => ShiftType::class,
    ];

    public function getTypeLabelAttribute(): string
    {
        return $this->type?->short() ?? '';
    }

    protected $appends = ['total_duration']; //API/Bladeで見える

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function originalUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_user_id');
    }

    /** 期間（[start,end]）にかかるものを取得 */
    public function scopeBetween(Builder $q, Carbon $start, Carbon $end): Builder
    {
        return $q->whereDate('event_date', '>=', $start->toDateString())
            ->whereDate('event_date', '<=', $end->toDateString());
    }

    public function leave(): BelongsTo
    {
        return $this->belongsTo(Leave::class, 'source_leave_id');
    }

    public function details()
    {
        return $this->hasMany(EventDetail::class);
    }

    // total_duration ("H:MM") から分数に変換して返す
    public function getTotalDurationAttribute(): ?string
    {
        $min = $this->total_minutes;
        if ($min === null) return null;
        $h = intdiv($min, 60);
        $m = $min % 60;
        return sprintf('%d:%02d', $h, $m); // 例: 465 → "7:45"
    }
}
