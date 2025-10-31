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
     * - type/status を extendedProps と classNames に反映（CSSで色分け）
     */
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $rows = Event::query()
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->with([
                'details.start',
                'details.lesson',
                'originalUser:id,first_name,family_name',
                'assignedUser:id,first_name,family_name'
            ])
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        $events = []; // CandidateEvent 配列（イベント枠、背景枠、モーダルなどに必要な情報）

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
                "fc-event-{$e->type}",   // 例: fc-event-overtime / fc-event-none_required / fc-event-regular_time など
                "status-{$e->status}",   // 例: status-pending / status-in_process / status-fixed / status-filled
            ];

            // ✅ 追加条件: pending/in_process + assigned_user_idあり
            if (
                in_array($e->status, ['pending', 'in_process'], true) &&
                !empty($e->assigned_user_id)
            ) {
                $classNames[] = 'status-assigned'; // 黄色枠専用クラス
            }

            //original_user_name, assigned_user_name の取得
            $orig = $e->originalUser;
            $origName = $orig ? trim(($orig->family_name ?? '') . ' ' . ($orig->first_name ?? '')) : null;
            //assigned_user_name の取得
            $assign = $e->assignedUser;
            $assignName = $assign ? trim(($assign->family_name ?? '') . ' ' . ($assign->first_name ?? '')) : null;

            $events[] = new CandidateEvent([
                'id'      => (string)$e->id, // FullCalendar の event.id としてセット（JS の e.id で拾えるように）
                'title'   => $title,
                'start'   => $startAt,
                'end'     => $endAt,
                'allDay'  => $isAllDay,
                'display' => 'auto',
                'classNames' => $classNames,
                'extendedProps' => [
                    'category'  => 'event',
                    'type'      => $e->type,
                    'school'    => $e->school_name,
                    'school_name' => $e->school_name,        // ▼ JS 側フォールバック（school_name 参照）とも整合させるために併記
                    'status'    => $e->status,               // pending / fixed / filled / in_process
                    'source_schedule_line_id' => $e->source_schedule_line_id,
                    'source_leave_id'         => $e->source_leave_id, // どのLeaveから生成されたか
                    'original_user_id'        => $e->original_user_id,
                    'assigned_user_id'        => $e->assigned_user_id,
                    'details'   => $details,
                    'sort_order' => $this->sortOrderForType($e->type),

                    'original_user_name'  => $origName,
                    'assigned_user_name'  => $assignName,

                    // ▼ モーダル表示用に最小追加
                    'event_id'        => $e->id,
                    'event_date'      => $ymd,
                    'start_time'      => $startHm,
                    'end_time'        => $endHm,
                    'total_duration'  => $e->total_duration,
                    'notes'           => $e->notes,
                    'title_raw'       => $e->title,
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
