<?php

namespace App\Services;

use App\Models\User;
use App\Services\Calendar\CalendarResolver;
use App\Services\Calendar\EventType;
use App\Services\Calendar\PlanGroup;
use Carbon\Carbon;

class ExpenseEligibilityService
{
    public function buildMonthlyFlags(User $user, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();

        // 失敗しても 500 にしない
        try {
            $events = app(CalendarResolver::class)->build($user, $start, $end);
        } catch (\Throwable $e) {
            report($e);
            $events = [];
        }

        $byDate = [];
        foreach ($events as $ev) {
            $date = substr($ev['start'] ?? '', 0, 10);
            if (!$date) continue;

            $plan = $ev['planGroup'] ?? null;
            $type = $ev['type'] ?? null;

            $byDate[$date]['regular_on'] = $byDate[$date]['regular_on'] ?? false;
            $byDate[$date]['event_on']   = $byDate[$date]['event_on']   ?? false;

            if ($plan === PlanGroup::REGULAR_PLAN && $type === EventType::ON) $byDate[$date]['regular_on'] = true;
            if ($plan === PlanGroup::EVENT        && $type === EventType::ON) $byDate[$date]['event_on']   = true;
        }

        // 通勤定期の取得もガード
        $passes = collect();
        try {
            if (method_exists($user, 'commuterPasses')) {
                $passes = $user->commuterPasses()
                    ->whereDate('date_to', '>=', $start)
                    ->whereDate('date_from', '<=', $end)->get();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $flags = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $ds = $d->toDateString();
            $hasPass = $passes->contains(fn($p) => $ds >= $p->date_from && $ds <= $p->date_to);
            $regOn = $byDate[$ds]['regular_on'] ?? false;
            $evtOn = $byDate[$ds]['event_on']   ?? false;

            if ($hasPass) {
                $flags[$ds] = ['normal' => $evtOn, 'reason' => $evtOn ? 'event_on' : 'pass_requires_event_on'];
            } else {
                $flags[$ds] = ['normal' => $regOn, 'reason' => $regOn ? 'regular_on' : 'no_regular'];
            }
        }
        return $flags;
    }
}
