<?php

namespace App\Services\Calendar\Providers;

use App\Models\CompanyClosure;
use App\Models\User;
use App\Services\Calendar\{CandidateEvent, EventType, PlanGroup};
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ClosureProvider implements CalendarEventProvider
{
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $events = [];
        $closures = CompanyClosure::between($start->toDateString(), $end->toDateString())->get();

        foreach ($closures as $c) {
            $period = CarbonPeriod::create($c->start_date, $c->end_date);
            $shown = 0;
            foreach ($period as $d) {
                $ymd = $d->toDateString();
                $title = $c->name;
                if (in_array($c->code, ['SB', 'WB'], true)) {
                    if ($shown < 5) {
                        $title = 'Fixed ALP';
                    }
                    $shown++;
                }
                $events[] = new CandidateEvent([
                    'title' => $title,
                    'start' => $ymd,
                    'allDay' => true,
                    'classNames' => ['fc-company-break'],
                    'extendedProps' => ['category' => '0_company', 'code' => $c->code, 'original_name' => $c->name],
                    'level' => 4,
                    'type' => EventType::BACKGROUND,
                    'planGroup' => PlanGroup::REGULAR_PLAN,
                ]);
            }
        }
        return $events;
    }
}
