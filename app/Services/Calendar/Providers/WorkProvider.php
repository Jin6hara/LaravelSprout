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
        $asgs = UserScheduleAssignment::with([
            'schedule.lines.details.start',
            'schedule.lines.details.lesson',
        ])
            ->where('user_id', $user->id)
            ->activeBetween($start->toDateString(), $end->toDateString())
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

            // ★ end_date が null の場合に対応
            $asg = $asgs->first(
                fn($a) =>
                $a->start_date->lte($cur) &&
                    (is_null($a->end_date) || $a->end_date->gte($cur))
            );

            if ($asg && $asg->schedule) {
                $lines = $asg->schedule->lines->filter(
                    fn($ln) =>
                    $ln->dow === $dow &&
                        $ln->effective_start->lte($cur) &&
                        // ★ effective_end が null の場合に対応
                        (is_null($ln->effective_end) || $ln->effective_end->gte($cur))
                );

                foreach ($lines as $ln) {
                    // line の時刻（文字列でもOKだが表記を安定化）
                    $lineStartHM = substr($ln->start_time, 0, 5); // 'HH:MM'
                    $lineEndHM   = substr($ln->end_time,   0, 5);
                    $lineStart   = Carbon::parse("$ymd $lineStartHM");
                    $lineEnd     = Carbon::parse("$ymd $lineEndHM");
                    $isSub       = strcasecmp($ln->school_name, 'Sub') === 0;

                    // === details を line 範囲でフィルタ ===
                    $lineStartMin = $toMinutes($lineStartHM);
                    $lineEndMin   = $toMinutes($lineEndHM);

                    $details = $ln->details
                        ->filter(function ($d) use ($toMinutes, $lineStartMin, $lineEndMin) {
                            $startObj = $d->start?->start_time; // cast: datetime:H:i
                            if (!$startObj) return false;
                            $hm = $startObj->format('H:i');
                            $m  = $toMinutes($hm);
                            return $m !== null && $lineStartMin !== null && $lineEndMin !== null
                                && $m >= $lineStartMin && $m <= $lineEndMin;
                        })
                        ->sortBy(fn($d) => $d->start->start_time) // H:i でOK
                        ->values()
                        ->map(function ($d) {
                            return [
                                'start_hm'    => $d->start->start_time->format('H:i'),
                                'lesson_code' => $d->lesson->lesson_code,
                                'lesson_name' => $d->lesson->lesson_name,
                                'lesson_min'  => $d->lesson->lesson_minute,
                                'lesson_type' => $d->lesson->lesson_type,
                            ];
                        })->all();

                    $events[] = new CandidateEvent([
                        'title'  => "{$ln->school_name} {$lineStart->format('H:i')}–{$lineEnd->format('H:i')}",
                        'start'  => $lineStart->toIso8601String(),
                        'end'    => $lineEnd->toIso8601String(),
                        'allDay' => false,
                        'classNames' => [$isSub ? 'fc-regular-shift-sub' : 'fc-regular-shift'],
                        'extendedProps' => [
                            'category' => '5_regular',
                            'location' => $ln->school_name,
                            'details'  => $details, // ← これをフロントで使う
                        ],
                        'level'     => 5,
                        'type'      => EventType::ON,
                        'planGroup' => PlanGroup::REGULAR_PLAN,
                        'dateKey'   => $ymd,
                    ]);
                }
            }

            $cur->addDay();
        }

        return $events;
    }
}
