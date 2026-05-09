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
        // 表示対象は requied/none_required両方（後者は消しもいいけどとりあえず残しておく）

        $scopeService  = app(\App\Services\CurrentScopeService::class);
        $districtId    = $scopeService->currentDistrictId();
        $departmentId  = $scopeService->currentDepartmentId();

        $rows = Event::query()
            ->where('assigned_user_id', $user->id)
            ->when($districtId,   fn($q) => $q->where('district_id',   $districtId))
            ->when($departmentId, fn($q) => $q->where('department_id', $departmentId))
            // status: fixed/filled のみ対象（cancelled 等はここで除外される）
            ->whereIn('status', ['fixed', 'filled'])
            // sub 条件を撤去
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->with(['details.lesson'])
            ->orderBy('event_date')->orderBy('start_time')
            ->get();

        $events = [];
        foreach ($rows as $e) {
            $ymd = $e->event_date->toDateString();

            // 表示名（school + 時間。無ければ title / sub をフォールバック）
            $title = $this->makeDisplayTitle(
                $e->title,
                $e->school_name,
                $e->start_time ? $e->start_time->format('H:i') : null,
                $e->end_time   ? $e->end_time->format('H:i')   : null,
                $e->type // ← 追加
            );

            // allDay 判定（start/end どちらか無ければ終日）
            $isAllDay = (is_null($e->start_time) || is_null($e->end_time));
            $startAt  = $isAllDay
                ? $ymd
                : Carbon::parse("{$ymd} {$e->start_time->format('H:i')}")->toIso8601String();
            $endAt    = $isAllDay
                ? Carbon::parse($ymd)->addDay()->toDateString() // FullCalendar の allDay 仕様
                : Carbon::parse("{$ymd} {$e->end_time->format('H:i')}")->toIso8601String();

            // details（WorkProvider と同じ形：start_hm / lesson_*）
            $details = collect($e->details)
                ->filter(fn($d) => !empty($d->start_time))
                ->sortBy(fn($d) => $d->start_time)
                ->values()
                ->map(function ($d) {
                    return [
                        'start_hm'    => substr($d->start_time, 0, 5),
                        'lesson_code' => $d->lesson?->lesson_code,
                        'lesson_name' => $d->lesson?->lesson_name,
                        'lesson_min'  => $d->lesson?->lesson_minute,
                        'lesson_type' => $d->lesson?->lesson_type,
                    ];
                })
                ->all();

            // 表示用クラス
            $classNames = ['fc-event-on', "fc-event-{$e->type}"];
            // 代行(sub)や残業(overtime)の強調など、CSSでデザイン分け可能

            $events[] = new CandidateEvent([
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
                    'status'    => $e->status,
                    'source_schedule_line_id' => $e->source_schedule_line_id,
                    'original_user_id'        => $e->original_user_id,
                    'details'   => $details,
                    // 週ビューの表示順制御（必要なら type ごとに値を調整）
                    'sort_order' => $this->sortOrderFortype($e->type),
                ],
                // Resolver 側設定（ベース250905FC）で level/type/planGroup は上書きされますが、
                // デフォルト値としてセットしておきます。
                'level'     => 1,
                'type'      => EventType::ON,
                'planGroup' => PlanGroup::EVENT,
                'dateKey'   => $ymd,
            ]);
        }

        return $events;
    }

    /**
     * 表示名を location + " " + HH:MM–HH:MM で生成
     * location/時間が無い場合は title や type でフォールバック
     */
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

        if ($title) return $title;

        return match ($type) {
            'overtime'       => '残業',
            'regular_time'   => '通常勤務',
            'special'        => '特別イベント',
            'schedule_change' => '時間変更',
            default          => 'イベント',
        };
    }

    /**
     * type ごとの並び順（必要に応じて調整）
     * 例：残業や代行を regular より上に出したい等
     */
    private function sortOrderForType(string $type): int
    {
        return match ($type) {
            'overtime'       => 10,
            'regular_time'   => 20,
            'special'        => 30,
            'schedule_change' => 40,
            default          => 50,
        };
    }
}
