<?php

namespace App\Services\Calendar\Providers;

use App\Models\RestPatternAdjustment;
use App\Models\User;
use App\Models\UserRestPattern;
use App\Services\Calendar\{CandidateEvent, EventType, PlanGroup};
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class AdjustingProvider implements CalendarEventProvider
{
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $assigns = UserRestPattern::with(['pattern.rules'])
            ->where('user_id', $user->id)
            ->activeBetween($start->toDateString(), $end->toDateString())
            ->orderBy('start_date')->get();

        $patternIds = $assigns->pluck('rest_pattern_id')->unique()->values();
        $adjs = RestPatternAdjustment::between($start->toDateString(), $end->toDateString())
            ->whereIn('rest_pattern_id', $patternIds)
            ->get()
            ->groupBy(['rest_pattern_id', fn($a) => $a->date->toDateString()]);

        $events = [];
        $cur = (clone $start)->startOfDay();
        while ($cur->lte($end)) {
            $ymd = $cur->toDateString();
            $assign = $assigns->first(fn($a) => $a->start_date->lte($cur) && (is_null($a->end_date) || $a->end_date->gte($cur)));
            if ($assign) {
                /** @var \App\Models\RestPatternAdjustment|null $adj */
                $adj = data_get($adjs, "{$assign->rest_pattern_id}.{$ymd}.0");
                if ($adj) {
                    if ($adj->kind === 'add_off') {
                        // 調整休日（所定追加）
                        $events[] = new CandidateEvent([
                            'title' => $adj->title ?: 'ORD',
                            'start' => $ymd,
                            'allDay' => true,
                            'classNames' => ['fc-off-prescribed'],
                            'extendedProps' => ['category' => '1_off', 'code' => 'ORD'],
                            'level' => 2,
                            'type' => EventType::BACKGROUND,
                            'planGroup' => PlanGroup::REGULAR_PLAN,
                        ]);
                    } elseif ($adj->kind === 'work_instead') {
                        // 調整出勤（黄・ON）
                        $events[] = new CandidateEvent([
                            'title' => $adj->title ?: '調整出勤（RWD）',
                            'start' => $ymd,
                            'allDay' => true,
                            'classNames' => ['fc-rwd'],
                            'extendedProps' => ['category' => '1_off', 'code' => 'RWD'],
                            'level' => 2,
                            'type' => EventType::ON,
                            'planGroup' => PlanGroup::REGULAR_PLAN,
                        ]);
                    }
                }
            }
            $cur->addDay();
        }
        return $events;
    }
}
