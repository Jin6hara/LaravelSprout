<?php

namespace App\Services\Calendar;

use App\Models\User;
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class ForecastResolver
{
    /** @var CalendarEventProvider[] */
    private array $providers = [];
    private array $meta;
    private array $rules;

    public function __construct(iterable $providers)
    {
        $this->providers = is_array($providers) ? $providers : iterator_to_array($providers);
        $this->meta  = config('calendar.providers', []);
        $this->rules = config('calendar.rules', []);
    }

    public function build(?User $user, Carbon $start, Carbon $end): array
    {
        $daily = []; // 'YYYY-MM-DD' => ['by_level' => [level => [event,array...]]]

        foreach ($this->providers as $p) {
            $cls = get_class($p);
            $defaults = $this->meta[$cls] ?? [];

            foreach ($p->provide($user, $start, $end) as $ev) {
                if (is_array($ev)) $ev = (object)$ev;

                // 既定適用（config最優先）
                $level     = $defaults['level'] ?? ($ev->level ?? 9);
                $type      = $ev->type ?? ($defaults['type'] ?? EventType::BACKGROUND);
                $planGroup = $defaults['plan'] ?? ($ev->planGroup ?? PlanGroup::REGULAR_PLAN);

                // FC配列化
                $arr = method_exists($ev, 'toArray') ? $ev->toArray() : get_object_vars($ev);
                $arr['extendedProps'] = $arr['extendedProps'] ?? [];
                $arr['extendedProps']['level'] = $arr['extendedProps']['level'] ?? $level;

                // 背景指定の補完
                if (!isset($arr['display']) && $type === EventType::BACKGROUND) {
                    $arr['display'] = 'background';
                }

                // --- ここが重要：全イベントを「日割り(single-day)」に展開 ---
                [$S, $E] = $this->eventRange($arr, $start, $end); // [start, endExclusive]
                $cur = $S->copy();
                while ($cur < $E) {
                    $d = $cur->toDateString();

                    $dayEvent = $arr;
                    $dayEvent['start'] = $d;
                    unset($dayEvent['end']); // 1日単位化
                    $daily[$d]['by_level'][$level][] = $dayEvent;

                    $cur->addDay();
                }
            }
        }

        // --- その日で「最小 level のイベントだけ」採用（holiday untouchable対応）---
        $out = [];
        foreach ($daily as $d => $bucket) {
            if (empty($bucket['by_level'])) continue;

            $levels = array_keys($bucket['by_level']);
            sort($levels);
            $minLevel = ($this->rules['holiday_untouchable'] ?? true) && in_array(1, $levels, true) ? 1 : $levels[0];

            foreach ($bucket['by_level'][$minLevel] as $ev) {
                $out[] = $ev;
            }
        }

        // 安定ソート：level → date → title
        usort($out, function ($a, $b) {
            $al = $a['extendedProps']['level'] ?? 9;
            $bl = $b['extendedProps']['level'] ?? 9;
            if ($al !== $bl) return $al <=> $bl;

            $ad = substr(($a['start'] ?? ''), 0, 10);
            $bd = substr(($b['start'] ?? ''), 0, 10);
            if ($ad !== $bd) return strcmp($ad, $bd);

            return strcmp(($a['title'] ?? ''), ($b['title'] ?? ''));
        });

        return $out;
    }

    /** @return array{0:Carbon,1:Carbon} [start, endExclusive] （クエリ範囲にクランプ）*/
    private function eventRange(array $ev, Carbon $reqStart, Carbon $reqEnd): array
    {
        $s = Carbon::parse(substr(($ev['start'] ?? ''), 0, 10) ?: $reqStart->toDateString())->startOfDay();
        $e = !empty($ev['end'])
            ? Carbon::parse(substr($ev['end'], 0, 10))->startOfDay()
            : $s->copy()->addDay();

        // リクエスト範囲にクランプ（reqEnd は排他）
        $S = $s->lessThan($reqStart) ? $reqStart->copy()->startOfDay() : $s;
        $E = $e->greaterThan($reqEnd) ? $reqEnd->copy()->startOfDay() : $e;

        if ($E <= $S) {
            $E = $S->copy()->addDay();
        } // 念のため

        return [$S, $E];
    }
}
