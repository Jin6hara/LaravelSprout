<?php

/**
 * ユーザーのスケジュールライン（通常勤務）をカレンダーイベントとして提供するプロバイダ。
 */
namespace App\Services\Calendar\Providers;

use App\Models\User;
use App\Models\ScheduleLine;
use App\Services\Calendar\{CandidateEvent, EventType, PlanGroup};
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class WorkProvider implements CalendarEventProvider
{
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $lines = ScheduleLine::with(['details.lesson'])
            ->where('user_id', $user->id)
            ->whereDate('effective_start', '<=', $end->toDateString())
            ->whereDate('effective_end',   '>=', $start->toDateString())
            ->get();

        $events = [];
        $cur = (clone $start)->startOfDay();

        // ヘルパ：'H:i' / 'H:i:s' どちらでも分→数値に
        $toMinutes = function (?string $hm) {
            if (!$hm) return null;
            $hm = substr($hm, 0, 5); // 'HH:MM'
            [$H, $M] = array_map('intval', explode(':', $hm));
            return $H * 60 + $M;
        };

        while ($cur->lte($end)) {
            $ymd = $cur->toDateString();
            $dow = (int)$cur->dayOfWeek; // 0..6

            $relevantLines = $lines->filter(
                fn($ln) =>
                $ln->dow === $dow &&
                    $ln->effective_start->lte($cur) &&
                    (is_null($ln->effective_end) || $ln->effective_end->gte($cur))
            );

            foreach ($relevantLines as $ln) {
                $lineStartHM = substr($ln->start_time, 0, 5);
                $lineEndHM   = substr($ln->end_time,   0, 5);
                $lineStart   = Carbon::parse("$ymd $lineStartHM");
                $lineEnd     = Carbon::parse("$ymd $lineEndHM");
                $lineStartMin = $toMinutes($lineStartHM);
                $lineEndMin   = $toMinutes($lineEndHM);

                $details = $ln->details
                    ->filter(function ($d) use ($toMinutes, $lineStartMin, $lineEndMin, $cur) {
                        $inRange = $d->effective_start->lte($cur) &&
                            (is_null($d->effective_end) || $d->effective_end->gte($cur));
                        if (!$inRange) return false;

                        if (!$d->start_time) return false;
                        $hm = substr($d->start_time, 0, 5);
                        $m  = $toMinutes($hm);
                        return $m !== null && $lineStartMin !== null && $lineEndMin !== null
                            && $m >= $lineStartMin && $m <= $lineEndMin;
                    })
                    ->sortBy(fn($d) => $d->start_time)
                    ->values()
                    ->map(function ($d) {
                        return [
                            'start_hm'    => substr($d->start_time, 0, 5),
                            'lesson_code' => $d->lesson->lesson_code,
                            'lesson_name' => $d->lesson->lesson_name,
                            'lesson_min'  => $d->lesson->lesson_minute,
                            'lesson_type' => $d->lesson->lesson_type,
                        ];
                    })->all();

                $isSub = strcasecmp($ln->school_name, 'Sub') === 0
                    || collect($details)->contains(fn($d) => ($d['lesson_code'] ?? '') === 'SUB');

                $events[] = new CandidateEvent([
                    'title'  => "{$ln->school_name} {$lineStart->format('H:i')}–{$lineEnd->format('H:i')}",
                    'start'  => $lineStart->toIso8601String(),
                    'end'    => $lineEnd->toIso8601String(),
                    'allDay' => false,
                    'classNames' => [$isSub ? 'fc-regular-shift-sub' : 'fc-regular-shift'],
                    'extendedProps' => [
                        'category' => '5_regular',
                        'location' => $ln->school_name,
                        'details'  => $details,
                    ],
                    'level'     => 5,
                    'type'      => EventType::ON,
                    'planGroup' => PlanGroup::REGULAR_PLAN,
                    'dateKey'   => $ymd,
                ]);
            }

            $cur->addDay();
        }

        return $events;
    }
}
