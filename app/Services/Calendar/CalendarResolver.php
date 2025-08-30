<?php

namespace App\Services\Calendar;

use App\Models\User;
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class CalendarResolver
{
    /** @var CalendarEventProvider[] */
    private array $providers = [];
    private array $meta;   // config('calendar.providers')
    private array $rules;  // config('calendar.rules')

    public function __construct(iterable $providers)
    {
        $this->providers = is_array($providers) ? $providers : iterator_to_array($providers);
        $this->meta  = config('calendar.providers', []);
        $this->rules = config('calendar.rules', []);
    }

    public function build(User $user, Carbon $start, Carbon $end): array
    {
        // 1) 各 Provider から候補を収集（既定メタを付与）
        $cands = [];
        foreach ($this->providers as $p) {
            $cls = get_class($p);
            $defaults = $this->meta[$cls] ?? [];
            foreach ($p->provide($user, $start, $end) as $ev) {
                // 既定適用（Provider側で上書き可）
                $ev->level     = $ev->level     ?? ($defaults['level'] ?? 9);
                $ev->type      = $ev->type      ?? ($defaults['type']  ?? EventType::BACKGROUND);
                $ev->planGroup = $ev->planGroup ?? ($defaults['plan']  ?? PlanGroup::REGULAR_PLAN);
                $cands[] = $ev;
            }
        }

        // 2) 日単位のバケツ
        $daily = []; // 'YYYY-MM-DD' => ['regular'=>CandidateEvent|null, 'on'=>[], 'off'=>[]]

        // 3) ★ regular_plan 全体（BACKGROUND も ON も含む）から level 最小を1つ選ぶ
        foreach ($cands as $ev) {
            if ($ev->planGroup !== PlanGroup::REGULAR_PLAN) continue;

            $d = $ev->dateKey;
            $daily[$d] ??= ['regular' => null, 'on' => [], 'off' => []];

            // 祝日 untouchable
            if (($this->rules['holiday_untouchable'] ?? true) && $daily[$d]['regular'] && $daily[$d]['regular']->level === 1) {
                continue;
            }

            if (!$daily[$d]['regular'] || $ev->level < $daily[$d]['regular']->level) {
                $daily[$d]['regular'] = $ev; // 勝者を入れ替え
            }
        }

        // 4) ★ EVENT グループのみ OFF/ON を合成
        foreach ($cands as $ev) {
            if ($ev->planGroup !== PlanGroup::EVENT) continue; // ← regular_plan の ON はここに入れない

            $d = $ev->dateKey;
            $daily[$d] ??= ['regular' => null, 'on' => [], 'off' => []];

            if ($ev->type === EventType::OFF) {
                $daily[$d]['off'][] = $ev;
                if ($this->rules['off_overrides_on'] ?? true) {
                    $daily[$d]['on'] = []; // 簡易：その日の EVENT-ON を全消し
                }
            } elseif ($ev->type === EventType::ON) {
                if ($this->rules['on_adds_to_background'] ?? true) {
                    $daily[$d]['on'][] = $ev;
                }
            }
        }

        // 5) 出力（regular 1つ + OFF + ON）
        $events = [];
        foreach ($daily as $bucket) {
            if (($this->rules['regular_plan_only_one_per_day'] ?? true) && $bucket['regular']) {
                $events[] = $bucket['regular']->toArray();
            }
            foreach ($bucket['off'] as $off) {
                $events[] = $off->toArray();
            }
            foreach ($bucket['on']  as $on) {
                $events[] = $on->toArray();
            }
        }

        // 並び安定化（level→start）
        usort($events, function ($a, $b) {
            $al = $a['extendedProps']['level'] ?? 9;
            $bl = $b['extendedProps']['level'] ?? 9;
            if ($al !== $bl) return $al <=> $bl;
            return strcmp(($a['start'] ?? ''), ($b['start'] ?? ''));
        });

        return $events;
    }
}
