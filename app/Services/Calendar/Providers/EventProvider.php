<?php

namespace App\Services\Calendar\Providers;

use App\Models\Event;
use App\Models\User;
use App\Services\Calendar\{CandidateEvent, EventType, PlanGroup};
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class EventProvider implements CalendarEventProvider
{
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $rows = Event::query()
            ->where('assigned_user_id', $user->id)
            ->between($start, $end)
            ->where('status', '!=', 'cancelled')
            ->orderBy('event_date')->orderBy('start_time')
            ->get();

        $events = [];
        foreach ($rows as $e) {
            $ymd = $e->event_date->toDateString();

            // 表示名（location + 時間）を Provider 側で生成
            $title = $this->makeDisplayTitle(
                $e->title,
                $e->school_name,
                $e->start_time ? $e->start_time->format('H:i') : null,
                $e->end_time   ? $e->end_time->format('H:i')   : null,
                $e->kind
            );

            // allDay判定（どちらか欠けたら終日扱い）
            $isAllDay = is_null($e->start_time) || is_null($e->end_time);

            // FullCalendar 仕様: allDay は end=翌日
            $startAt = $isAllDay
                ? $ymd
                : Carbon::parse("{$ymd} {$e->start_time->format('H:i')}")->toIso8601String();

            $endAt = $isAllDay
                ? Carbon::parse($ymd)->addDay()->toDateString()
                : Carbon::parse("{$ymd} {$e->end_time->format('H:i')}")->toIso8601String();

            $classNames = ['fc-event-on', "fc-event-{$e->kind}"];

            $events[] = new CandidateEvent([
                'title'   => $title,
                'start'   => $startAt,
                'end'     => $endAt,
                'allDay'  => $isAllDay,
                'display' => 'auto',
                'classNames' => $classNames,
                'extendedProps' => [
                    'category' => 'event',
                    'kind'     => $e->kind,              // overtime/sub/special/regular_copy/other
                    'school'   => $e->school_name,
                    'status'   => $e->status,
                    'source_schedule_line_id' => $e->source_schedule_line_id,
                    'original_user_id'        => $e->original_user_id,
                    'sort_order' => 0,
                ],
                'level'     => 1,                         // config で上書きされます（ベース250831FC）
                'type'      => EventType::ON,             // 同上
                'planGroup' => PlanGroup::EVENT,          // 同上
                'dateKey'   => $ymd,                      // Resolverのバケツ分け安定化
            ]);
        }

        return $events;
    }

    /**
     * 表示名を location + " " + HH:MM–HH:MM で生成
     * location/時間が無い場合は title や kind でフォールバック
     */
    private function makeDisplayTitle(
        ?string $title,
        ?string $schoolName,
        ?string $startHm,
        ?string $endHm,
        ?string $kind
    ): string {
        $time = ($startHm && $endHm) ? "{$startHm}–{$endHm}" : null;

        if ($schoolName && $time) return "{$schoolName} {$time}";
        if ($schoolName)          return $schoolName;
        if ($time)                return $time;

        // fallback（title > kind）
        if ($title) return $title;

        return match ($kind) {
            'overtime'      => '残業',
            'sub'           => '代行',
            'special'       => '特別イベント',
            'regular_copy'  => '正規コマ（コピー）',
            default         => 'イベント',
        };
    }
}
