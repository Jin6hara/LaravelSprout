<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\Calendar\EventType;
use App\Services\Calendar\PlanGroup;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_date',
        'title',
        'school_name',
        'start_time',
        'end_time',
        'kind',
        'assigned_user_id',
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

    /** FullCalendar用配列（Resolverが配列も受けるのでOK） */
    public function toCalendarArray(): array
    {
        $isAllDay = is_null($this->start_time) || is_null($this->end_time);

        $start = $isAllDay
            ? $this->event_date->toDateString()
            : Carbon::parse($this->event_date->toDateString() . ' ' . $this->start_time->format('H:i'))->toIso8601String();

        $end = $isAllDay
            ? $this->event_date->copy()->addDay()->toDateString()  // allDay は翌日0:00終端
            : Carbon::parse($this->event_date->toDateString() . ' ' . $this->end_time->format('H:i'))->toIso8601String();

        return [
            'title'   => $this->title ?? $this->displayTitle(),
            'start'   => $start,
            'end'     => $end,
            'allDay'  => $isAllDay,
            'display' => 'auto',
            'classNames' => ['fc-event-on', "fc-event-{$this->kind}"],
            'extendedProps' => [
                'category' => 'event',
                'plan'     => PlanGroup::EVENT,
                'type'     => EventType::ON,
                'level'    => 1,
                'sort_order' => 0,
                'event' => [
                    'id'     => $this->id,
                    'kind'   => $this->kind,
                    'school' => $this->school_name,
                    'status' => $this->status,
                    'source_schedule_line_id' => $this->source_schedule_line_id,
                    'original_user_id'        => $this->original_user_id,
                ],
            ],
            // Resolver用のキー（無くてもResolverが補完するが、付けておくと安定）
            'dateKey' => $this->event_date->toDateString(),
        ];
    }

    public function displayTitle(): string
    {
        // 校舎名
        $school = $this->school_name ?? '';

        // 時間フォーマット（存在すれば）
        $time = null;
        if ($this->start_time && $this->end_time) {
            $time = sprintf(
                '%s–%s',
                \Carbon\Carbon::parse($this->start_time)->format('H:i'),
                \Carbon\Carbon::parse($this->end_time)->format('H:i')
            );
        }

        // location + 時間
        if ($school && $time) {
            return "{$school} {$time}";
        } elseif ($school) {
            return $school;
        } elseif ($time) {
            return $time;
        }

        // fallback（種別）
        return match ($this->kind) {
            'overtime'      => '残業',
            'sub'           => '代行',
            'special'       => $this->title ?? '特別イベント',
            'regular_copy'  => '正規コマ（コピー）',
            default         => $this->title ?? 'イベント',
        };
    }
}
