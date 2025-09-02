<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Leave extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'start_date',
        'end_date',
        'kind',
        'excused',
        'special_type',
        'reason',
        'time_start',
        'time_end',
        'status',
        'approved_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'time_start' => 'datetime:H:i',
        'time_end'   => 'datetime:H:i',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function scopeBetween(Builder $q, Carbon $start, Carbon $end): Builder
    {
        // (start<=end) AND (end>=start) の期間重なり判定
        return $q->whereDate('start_date', '<=', $end->toDateString())
            ->where(function ($qq) use ($start) {
                $qq->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $start->toDateString());
            });
    }

    public function eachDate(): \Generator
    {
        $from = $this->start_date;
        $to   = $this->end_date ?: $this->start_date;
        $period = CarbonPeriod::create($from, $to);
        foreach ($period as $d) yield $d->copy();
    }

    public function isAllDay(): bool
    {
        return is_null($this->time_start) || is_null($this->time_end);
    }

    public function displayTitle(): string
    {
        return match ($this->kind) {
            'paid'    => '有給',
            'special' => $this->special_type ? "特別休暇（{$this->special_type}）" : '特別休暇',
            'absence' => '欠席',
            'late'    => '遅刻',
            default   => '休暇',
        };
    }
}
