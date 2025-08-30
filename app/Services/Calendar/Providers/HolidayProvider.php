<?php

namespace App\Services\Calendar\Providers;

use App\Models\Holiday;
use App\Models\User;
use App\Services\Calendar\{CandidateEvent, EventType, PlanGroup};
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class HolidayProvider implements CalendarEventProvider
{
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        return Holiday::between($start->toDateString(), $end->toDateString())
            ->get()
            ->map(function ($h) {
                return new CandidateEvent([
                    'title'  => $h->name,
                    'start'  => $h->date,
                    'allDay' => true,
                    'classNames' => ['fc-holiday'],
                    'extendedProps' => ['category' => 'regular', 'type' => 'holiday'],
                    'level' => 1,
                    'type'  => EventType::BACKGROUND,
                    'planGroup' => PlanGroup::REGULAR_PLAN,
                ]);
            })->all();
    }
}
