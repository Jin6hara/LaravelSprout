<?php

namespace App\Services\Calendar\Providers;

use App\Models\Event;
use App\Models\User;
use App\Services\Calendar\{CandidateEvent, EventType, PlanGroup};
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class AllEventProvider implements CalendarEventProvider
{
    /**
     * 期間内の「全イベント（全ユーザー分）」を取得して返却
     * - EventDetails があれば details として展開（WorkProvider と同一形）
     * - sub/type/status を extendedProps と classNames に反映（CSSで色分け）
     */
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $rows = Event::query()
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            // status は列挙：pending/fixed/filled/in_process（cancelledは仕様外なので除外不要）
            ->with(['details.start', 'details.lesson'])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        $events = [];

        foreach ($rows as $e) {
            $ymd = $e->event_date->toDateString();

            $startHm = $e->start_time?->format('H:i');
            $endHm   = $e->end_time?->format('H:i');

            $title = $this->makeDisplayTitle(
                $e->title,
                $e->school_name,
                $startHm,
                $endHm,
                $e->type
            );

            // allDay（どちらか欠けたら終日扱い）
            $isAllDay = (is_null($e->start_time) || is_null($e->end_time));
            $startAt  = $isAllDay
                ? $ymd
                : Carbon::parse("{$ymd} {$startHm}")->toIso8601String();
            $endAt    = $isAllDay
                ? Carbon::parse($ymd)->addDay()->toDateString()
                : Carbon::parse("{$ymd} {$endHm}")->toIso8601String();

            // EventDetails を time順にマップ
            $details = collect($e->details)
                ->filter(fn($d) => !empty($d->start?->start_time))
                ->sortBy(fn($d) => $d->start->start_time)
                ->values()
                ->map(function ($d) {
                    return [
                        'start_hm'    => $d->start->start_time->format('H:i'),
                        'lesson_code' => $d->lesson?->lesson_code,
                        'lesson_name' => $d->lesson?->lesson_name,
                        'lesson_min'  => $d->lesson?->lesson_minute,
                        'lesson_type' => $d->lesson?->lesson_type,
                    ];
                })
                ->all();

            // CSS用クラス（色分け/枠線用）
            $classNames = [
                'fc-event-on',
                "fc-event-{$e->type}",         // 例: fc-event-overtime
                "sub-{$e->sub}",               // 例: sub-required / sub-none_required / sub-other
                "status-{$e->status}",         // 例: status-pending / status-in_process / status-fixed / status-filled
            ];

            $events[] = new CandidateEvent([
                'id'      => 'ev:' . $e->id,   // ★追加
                'title'   => $title,
                'start'   => $startAt,
                'end'     => $endAt,
                'allDay'  => $isAllDay,
                'display' => 'auto',
                'classNames' => $classNames,
                'extendedProps' => [
                    'category'  => 'event',
                    'type'      => $e->type,           // overtime/sub/special/other
                    'school'    => $e->school_name,
                    'status'    => $e->status,
                    'source_schedule_line_id' => $e->source_schedule_line_id,
                    'original_user_id'        => $e->original_user_id,
                    'details'   => $details,
                    // 週ビューの表示順制御（必要なら type ごとに値を調整）
                    'sort_order' => $this->sortOrderFortype($e->type),
                ],
                // Resolver 側で上書きされても良いようにデフォ値を付与
                'level'     => 1,
                'type'      => EventType::ON,
                'planGroup' => PlanGroup::EVENT,
                'dateKey'   => $ymd,
            ]);
        }

        return $events;
    }

    private function makeDisplayTitle(
        ?string $title,
        ?string $schoolName,
        ?string $startHm,
        ?string $endHm,
        ?string $type
    ): string {
        $time = ($startHm && $endHm) ? "{$startHm}–{$endHm}" : null;
        if ($schoolName && $time) return "{$schoolName} {$time}";
        if ($schoolName)          return $schoolName;
        if ($time)                return $time;
        if ($title)               return $title;
        return match ($type) {
            'overtime'        => '残業',
            'regular_time'    => '通常勤務',
            'special'         => '特別イベント',
            'schedule_change' => '時間変更',
            default           => 'イベント',
        };
    }

    private function sortOrderForType(string $type): int
    {
        return match ($type) {
            'overtime'        => 10,
            'regular_time'    => 20,
            'special'         => 30,
            'schedule_change' => 40,
            default           => 50,
        };
    }
}
