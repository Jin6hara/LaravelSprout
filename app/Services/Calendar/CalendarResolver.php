<?php

/**
 * 複数の CalendarEventProvider を集約し、優先度ルールに従いカレンダーイベント一覧を構築するリゾルバ。
 */
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
        // 1) Provider収集 + 既定メタ付与（★config最優先）
        $cands = [];
        foreach ($this->providers as $p) {
            $cls = get_class($p);
            $defaults = $this->meta[$cls] ?? [];
            foreach ($p->provide($user, $start, $end) as $ev) {

                // ★ 追加：配列でも動くように軽く正規化
                //  - ここは “読み取りのみ” なので、配列は (object) キャストでOK
                if (is_array($ev)) $ev = (object) $ev;

                // ★ ここから先は従来通り
                $ev->level     = $defaults['level'] ?? ($ev->level ?? 9);

                // type: 基本は config、ただし Provider が動的に指定している場合（例:RWD=ON）はそれを優先
                $ev->type      = $ev->type ?? ($defaults['type'] ?? EventType::BACKGROUND);

                // plan: config を最優先（通常は固定でOK）
                $ev->planGroup = $defaults['plan'] ?? ($ev->planGroup ?? PlanGroup::REGULAR_PLAN);

                $cands[] = $ev;
            }
        }


        // 2) 日単位のバケツ
        $daily = []; // 'YYYY-MM-DD' => ['regular'=>CandidateEvent|null, 'on'=>[], 'off'=>[]]

        foreach ($cands as $ev) {

            // ★ 追加: dateKey が無ければ start から補完（YYYY-MM-DD）
            if (empty($ev->dateKey)) {
                // allDay は 0:00 始まり想定、ISO/日付どちらでもOKにしておく
                $startStr = is_string($ev->start) ? $ev->start : (string)($ev->start ?? '');
                $evDate   = substr($startStr, 0, 10); // "YYYY-MM-DD"
                $ev->dateKey = $evDate ?: now()->toDateString(); // 最後の保険
            }
            
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
        $push = function ($ev) use (&$events) {
            if (is_object($ev) && method_exists($ev, 'toArray')) {
                $events[] = $ev->toArray();
            } else {
                // ★ 配列/オブジェクトを FullCalendar 互換の配列へ最低限整形
                //   必要キー: title, start, end, allDay(optional), classNames(optional), extendedProps(optional)
                $arr = is_array($ev) ? $ev : get_object_vars($ev);

                // extendedProps の sort_order が無ければ自動補完
                $arr['extendedProps'] = $arr['extendedProps'] ?? [];
                $arr['extendedProps']['level'] = $arr['extendedProps']['level'] ?? ($arr['level'] ?? 9);
                $arr['extendedProps']['type'] = $arr['extendedProps']['type'] ?? ($arr['type'] ?? null);
                $arr['extendedProps']['plan_group'] = $arr['extendedProps']['plan_group'] ?? ($arr['planGroup'] ?? null);

                $events[] = $arr;
            }
        };

        foreach ($daily as $bucket) {
            foreach ($bucket['reg_bg'] as $bg) {
                $push($bg);
            }
            foreach ($bucket['event_off'] as $o) {
                $push($o);
            } // OFFは表示
            foreach ($bucket['reg_on'] as $on) {
                $push($on);
            }
            foreach ($bucket['event_on'] as $e) {
                $push($e);
            } // EVENT ONは重ねて表示
        }

        // 並び安定化（同日跨ぎ用に level→start）
        usort($events, function ($a, $b) {
            $al = $a['extendedProps']['level'] ?? 9;
            $bl = $b['extendedProps']['level'] ?? 9;
            if ($al !== $bl) return $al <=> $bl;

            // ★ 同一level内は date(YYYY-MM-DD) → start → title で安定ソート
            $ad = substr(($a['start'] ?? ''), 0, 10);
            $bd = substr(($b['start'] ?? ''), 0, 10);
            if ($ad !== $bd) return strcmp($ad, $bd);

            $as = $a['start'] ?? '';
            $bs = $b['start'] ?? '';
            if ($as !== $bs) return strcmp($as, $bs);

            return strcmp(($a['title'] ?? ''), ($b['title'] ?? ''));
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
