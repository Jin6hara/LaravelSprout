<?php

namespace App\Services\Calendar\Providers;

use App\Models\Leave;
use App\Models\User;
use App\Services\Calendar\EventType;
use App\Services\Calendar\PlanGroup;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LeaveProvider
{
    public function __construct(
        private readonly array $meta = [
            'level' => 1, // ベース250831FC指示
            'type'  => EventType::OFF,
            'plan'  => PlanGroup::EVENT,
        ]
    ) {}

    /**
     * @return \Illuminate\Support\Collection<int, array> FullCalendar event[]
     */
    public function provide(User $user, Carbon $start, Carbon $end): Collection
    {
        $leaves = Leave::query()
            ->where('user_id', $user->id)
            ->approved()
            ->between($start, $end)
            ->get();

        $events = collect();

        foreach ($leaves as $leave) {
            foreach ($leave->eachDate() as $date) {
                // allDay のときは end は翌日（FullCalendar仕様）
                $isAllDay = $leave->isAllDay();
                $startAt  = $isAllDay
                    ? $date->toDateString()
                    : Carbon::parse($date->toDateString() . ' ' . $leave->time_start)->toIso8601String();

                $endAt    = $isAllDay
                    ? $date->copy()->addDay()->toDateString()
                    : Carbon::parse($date->toDateString() . ' ' . $leave->time_end)->toIso8601String();

                $classes = ['fc-leave', "fc-leave-{$leave->kind}"];
                if ($leave->excused !== 'unknown') $classes[] = "fc-leave-{$leave->excused}";

                $events->push([
                    'title'   => $leave->displayTitle(),
                    'start'   => $startAt,
                    'end'     => $endAt,
                    'allDay'  => $isAllDay,
                    'display' => 'auto',
                    'classNames' => $classes,
                    'extendedProps' => [
                        'category' => 'leave',
                        'plan'     => $this->meta['plan'],
                        'type'     => $this->meta['type'],
                        'level'    => $this->meta['level'],
                        'sort_order' => 0, // eventOrder用
                        'leave'    => [
                            'id'           => $leave->id,
                            'kind'         => $leave->kind,
                            'excused'      => $leave->excused,
                            'special_type' => $leave->special_type,
                            'reason'       => $leave->reason,
                        ],
                    ],
                ]);
            }
        }

        return $events;
    }
}
