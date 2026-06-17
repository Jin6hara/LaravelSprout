<?php

/**
 * 会社休暇（CompanyClosure）の期間をカレンダーイベントとして提供するプロバイダ。
 */
namespace App\Services\Calendar\Providers;

use App\Models\CompanyClosure;
use App\Models\User;
use App\Services\Calendar\{CandidateEvent, EventType, PlanGroup};
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

// app/Services/Calendar/Providers/ClosureProvider.php
class ClosureProvider implements CalendarEventProvider
{
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $events = [];
        $closures = CompanyClosure::between($start->toDateString(), $end->toDateString())->get();

        foreach ($closures as $c) {
            $period = CarbonPeriod::create($c->start_date, $c->end_date);
            foreach ($period as $d) {
                $ymd = $d->toDateString();

                // ★ ここでは常に元名。Fixed ALP の判定は後段（Resolver）に委ねる
                $events[] = new CandidateEvent([
                    'title'  => $c->name,
                    'start'  => $ymd,
                    'allDay' => true,
                    'classNames' => ['fc-company-break'],
                    'extendedProps' => [
                        'category'      => '0_company',
                        'kind'          => 'company_break', // ← 判定用
                        'closure_id'    => $c->id,          // ← 同一休暇を区別
                        'closure_code'  => $c->code,        // ← SB / WB 判定
                        'original_name' => $c->name,
                    ],
                    'level'     => 4,
                    'type'      => EventType::BACKGROUND,
                    'planGroup' => PlanGroup::REGULAR_PLAN,
                ]);
            }
        }
        return $events;
    }
}
