<?php

namespace App\Services\Calendar\Providers;

use App\Models\User;
use App\Models\UserRestPattern;
use App\Services\Calendar\{CandidateEvent, EventType, PlanGroup};
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class OffDayProvider implements CalendarEventProvider
{
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $assigns = UserRestPattern::with(['pattern.rules'])
            ->where('user_id', $user->id)
            ->activeBetween($start->toDateString(), $end->toDateString())
            ->orderBy('start_date')->get();

        $events = [];
        $cur = (clone $start)->startOfDay();
        while ($cur->lte($end)) {
            $ymd = $cur->toDateString();
            $assign = $assigns->first(fn($a) => $a->start_date->lte($cur) && (is_null($a->end_date) || $a->end_date->gte($cur)));
            if ($assign && $assign->pattern) {
                $w = (int)$cur->dayOfWeek;
                $rule = $assign->pattern->rules->firstWhere('weekday', $w);
                if ($rule && $rule->kind !== 'work') {
                    $isStat = $rule->kind === 'statutory_off';
                    $events[] = new CandidateEvent([
                        'title'  => $isStat ? 'LRD' : 'ORD',
                        'start'  => $ymd,
                        'allDay' => true,
                        'classNames' => [$isStat ? 'fc-off-statutory' : 'fc-off-prescribed'],
                        'extendedProps' => ['category' => '1_off', 'type' => $isStat ? 'statutory' : 'prescribed'],
                        'level' => 3,
                        'type'  => EventType::BACKGROUND,
                        'planGroup' => PlanGroup::REGULAR_PLAN,
                    ]);
                }
            }
            $cur->addDay();
        }
        return $events;
    }
}
