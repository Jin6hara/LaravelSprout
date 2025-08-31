<?php

namespace App\Services\Calendar\Providers;

use App\Models\User;
use App\Models\UserScheduleAssignment;
use App\Services\Calendar\{CandidateEvent, EventType, PlanGroup};
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class WorkProvider implements CalendarEventProvider
{
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $asgs = UserScheduleAssignment::with(['schedule.lines'])
            ->where('user_id', $user->id)
            ->activeBetween($start->toDateString(), $end->toDateString())
            ->get();

        $events = [];
        $cur = (clone $start)->startOfDay();
        while ($cur->lte($end)) {
            $ymd = $cur->toDateString();

            $asg = $asgs->first(fn($a) => $a->start_date->lte($cur) && $a->end_date->gte($cur));
            if ($asg && $asg->schedule) {
                $dow = (int)$cur->dayOfWeek; // 0..6
                $lines = $asg->schedule->lines->filter(
                    fn($ln) =>
                    $ln->dow === $dow &&
                        $ln->effective_start->lte($cur) &&
                        $ln->effective_end->gte($cur)
                );

                foreach ($lines as $ln) {
                    $startAt = Carbon::parse("$ymd {$ln->start_time}");
                    $endAt   = Carbon::parse("$ymd {$ln->end_time}");
                    $isSub = strcasecmp($ln->school_name, 'Sub') === 0;

                    $events[] = new CandidateEvent([
                        'title' => "{$ln->school_name} {$startAt->format('H:i')}–{$endAt->format('H:i')}",
                        'start' => $startAt->toIso8601String(),
                        'end'   => $endAt->toIso8601String(),
                        'eventTimeFormat' => false, // 時間表示しない（CSSでも無効化するが保険でJSでも）
                        'allDay' => false,
                        'classNames' => [$isSub ? 'fc-regular-shift-sub' : 'fc-regular-shift'],
                        'extendedProps' => ['category' => '5_regular', 'location' => $ln->school_name],
                        'level' => 5,
                        'type' => EventType::ON,
                        'planGroup' => PlanGroup::REGULAR_PLAN,
                    ]);
                }
            }

            $cur->addDay();
        }

        return $events;
    }
}
