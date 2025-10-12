<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_date',
        'title',
        'school_name',
        'start_time',
        'end_time',
        'total_duration',
        'type',
        'assigned_user_id',
        'sub',
        'original_user_id',
        'source_schedule_line_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'event_date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time'   => 'datetime:H:i',
    ];

    protected $appends = ['total_minutes'];

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

    // Total Duration用表示整形: start_time / end_time を "H:i" に見せたい場合（保存はDBのまま）
    public function getStartTimeAttribute($value): ?string
    {
        return $value ? substr($value, 0, 5) : null; // "HH:MM"
    }
    public function getEndTimeAttribute($value): ?string
    {
        return $value ? substr($value, 0, 5) : null;
    }
}
