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

        foreach ($cands as $ev) {
            $d = $ev->dateKey;
            $daily[$d] ??= ['regular_by_level' => [], 'reg_bg' => [], 'reg_on' => [], 'event_off' => [], 'event_on' => []];

            if ($ev->planGroup === PlanGroup::REGULAR_PLAN) {
                $daily[$d]['regular_by_level'][$ev->level] ??= [];
                $daily[$d]['regular_by_level'][$ev->level][] = $ev;
            } else { // EVENT グループ
                if ($ev->type === EventType::OFF) {
                    $daily[$d]['event_off'][] = $ev;
                } elseif ($ev->type === EventType::ON) {
                    $daily[$d]['event_on'][] = $ev;
                }
            }
        }

        // 3) regular_plan の「最小 level の“すべて”」を勝者として採用
        foreach ($daily as $d => &$bucket) {
            if (empty($bucket['regular_by_level'])) continue;

            $levels = array_keys($bucket['regular_by_level']);
            sort($levels); // 小さい = 強い
            // 祝日untouchable: level1があれば1択
            $minLevel = ($this->rules['holiday_untouchable'] ?? true) && in_array(1, $levels, true) ? 1 : $levels[0];

            $winners = $bucket['regular_by_level'][$minLevel];

            // 勝者を背景/ONに分配（複数可）
            foreach ($winners as $ev) {
                if ($ev->type === EventType::BACKGROUND) {
                    $bucket['reg_bg'][] = $ev;
                } elseif ($ev->type === EventType::ON) {
                    $bucket['reg_on'][] = $ev;
                }
            }

            // regular_plan.on は早い時間順に
            usort($bucket['reg_on'], function ($a, $b) {
                return strcmp($a->start, $b->start);
            });
        }
        unset($bucket);

        // 4) ルール適用
        foreach ($daily as $d => &$bucket) {
            // 4-1) event.off は regular_plan.on を全て打ち消す（event.on は消さない）
            if (!empty($bucket['event_off']) && ($this->rules['off_overrides_on'] ?? true)) {
                $bucket['reg_on'] = [];
            }

            // 4-2) event.on は regular_plan.on と1分でも重なれば、その regular_plan.on を丸ごと削除
            if (!empty($bucket['event_on'])) {
                $bucket['reg_on'] = $this->filterOutOverlappedRegularOn($bucket['reg_on'], $bucket['event_on']);
                // event.on は background と共存（何もしない）
                // 表示順のため event_on も時間順に
                usort($bucket['reg_on'], fn($a, $b) => strcmp($a->start, $b->start));
            }
            foreach ($bucket['reg_on'] as $i => $ev) {
                $ev->extendedProps['sort_order'] = $i;
            }
        }
        unset($bucket);

        // 5) regular_plan 勝者の中で「会社休暇(SB/WB)」は表示順に5件までFixed ALP
        $winners = [];
        foreach ($daily as $date => $bucket) {
            foreach ($bucket['reg_bg'] as $bg) {
                $kind = $bg->extendedProps['kind'] ?? null;
                $code = $bg->extendedProps['closure_code'] ?? null;
                $cid  = $bg->extendedProps['closure_id'] ?? null;
                if ($kind === 'company_break' && in_array($code, ['SB', 'WB'], true)) {
                    $winners[] = [$date, $cid, $bg];
                }
            }
            foreach ($bucket['reg_on'] as $on) {
                $kind = $on->extendedProps['kind'] ?? null;
                $code = $on->extendedProps['closure_code'] ?? null;
                $cid  = $on->extendedProps['closure_id'] ?? null;
                if ($kind === 'company_break' && in_array($code, ['SB', 'WB'], true)) {
                    $winners[] = [$date, $cid, $on];
                }
            }
        }
        usort($winners, fn($a, $b) => strcmp($a[0], $b[0])); // 日付順

        $visibleCountPerClosure = [];
        foreach ($winners as [$date, $cid, $ev]) {
            $key = $cid ?: 'default';
            $visibleCountPerClosure[$key] = ($visibleCountPerClosure[$key] ?? 0) + 1;
            if ($visibleCountPerClosure[$key] <= 5) {
                $ev->title = 'Fixed ALP';
                $ev->extendedProps['is_fixed_alp'] = true;
            } else {
                $ev->extendedProps['is_fixed_alp'] = false;
            }
        }

        // 6) 出力へ
        $events = [];
        foreach ($daily as $bucket) {
            // 背景（regular_plan 最小levelのもの全部）
            foreach ($bucket['reg_bg'] as $bg) {
                $events[] = $bg->toArray();
            }
            // 有給などOFF（表示）
            foreach ($bucket['event_off'] as $off) {
                $events[] = $off->toArray();
            }
            // regular_plan.on（生き残った分）
            foreach ($bucket['reg_on'] as $on) {
                $events[] = $on->toArray();
            }
            // event.on（重ねて表示）
            foreach ($bucket['event_on'] as $eon) {
                $events[] = $eon->toArray();
            }
        }

        // 並び安定化（同日跨ぎ用に level→start）
        usort($events, function ($a, $b) {
            $al = $a['extendedProps']['level'] ?? 9;
            $bl = $b['extendedProps']['level'] ?? 9;
            if ($al !== $bl) return $al <=> $bl;
            return strcmp(($a['start'] ?? ''), ($b['start'] ?? ''));
        });

        return $events;
    }

    // === ヘルパー：event.on が regular_plan.on を打ち消す（時間重複なら除外） ===
    private function filterOutOverlappedRegularOn(array $regularOn, array $eventOn): array
    {
        // それぞれの [start, end) を Carbon で比較（end が無い/同一なら自動補完）
        $toInterval = function ($ev) {
            $s = \Carbon\Carbon::parse($ev->start);
            $e = $ev->end ? \Carbon\Carbon::parse($ev->end) : (clone $s)->addMinute(); // 念のため最小1分
            return [$s, $e];
        };

        $result = [];
        foreach ($regularOn as $r) {
            [$rs, $re] = $toInterval($r);
            $overlapped = false;
            foreach ($eventOn as $eo) {
                [$es, $ee] = $toInterval($eo);
                // 1分でも重なれば除外（[rs,re) × [es,ee) の交差）
                if ($rs < $ee && $es < $re) {
                    $overlapped = true;
                    break;
                }
            }
            if (!$overlapped) $result[] = $r;
        }
        return $result;
    }
}
