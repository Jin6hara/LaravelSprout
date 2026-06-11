<?php

/**
 * 欠勤・休暇レコードを管理するモデル。
 */
namespace App\Models;

use App\Enums\LeaveKind;
use App\Enums\LeaveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Leave extends Model
{
    use HasFactory;

    public ?string $generatedShiftAction = null;

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
        'handle_type',
        'district_id',
        'department_id',
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

    public function generatedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'source_leave_id');
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', LeaveStatus::Approved->value);
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

    public function displayTitle(): string //表示タイトルは再利用のためモデルに置くのが推奨される
    {
        return LeaveKind::tryFrom((string) $this->kind)?->calendarTitle($this->special_type) ?? 'Absence';
    }

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }

    public function attachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }
}
