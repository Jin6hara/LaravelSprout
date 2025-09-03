<?php

namespace App\Services\Calendar\Providers;

use App\Models\Event;
use App\Models\User;
use App\Services\Calendar\Contracts\CalendarEventProvider;
use App\Services\Calendar\EventType;
use App\Services\Calendar\PlanGroup;
use Carbon\Carbon;

class EventProvider implements CalendarEventProvider
{
    // ★ 戻り型を array に（interface と一致させる）
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $rows = Event::query()
            ->where('assigned_user_id', $user->id)
            ->between($start, $end)
            ->where('status', '!=', 'cancelled')
            ->orderBy('event_date')->orderBy('start_time')
            ->get();

        // Resolver は配列/オブジェクトどちらも受けられる構えがあるので、
        // ここでは “object化” してから配列に詰めて返す。
        $items = $rows->map(function (Event $e) {
            $arr = $e->toCalendarArray();

            // 明示メタ（configで上書きされてもOK）
            $arr['extendedProps']['plan']  = PlanGroup::EVENT;
            $arr['extendedProps']['type']  = EventType::ON;
            $arr['extendedProps']['level'] = 1;

            // ★ Resolver がプロパティアクセスするので object 化
            return (object) $arr;
        });

        // ★ Collection → array（interface準拠）
        return $items->values()->all();
    }
}
