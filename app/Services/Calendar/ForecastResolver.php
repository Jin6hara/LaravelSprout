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
        $this->meta  = config('calendar_forecast.providers', []);
        $this->rules = config('calendar_forecast.rules', []);
    }

    /**
     * 背景(BACKGROUND)は日割り→日別最小levelのみ採用
     * ON(Event)は圧縮せず全件をそのまま返す
     */
    public function build(?User $user, Carbon $start, Carbon $end): array
    {
        // 背景は日別にバケット、ONは全件素通し
        $backgroundDaily = []; // 'YYYY-MM-DD' => ['by_level' => [level => [event,array...]]]
        $onEvents = [];

        foreach ($this->providers as $p) {
            $cls = get_class($p);
            $defaults = $this->meta[$cls] ?? [];

            foreach ($p->provide($user, $start, $end) as $ev) {
                if (is_array($ev)) $ev = (object)$ev;

                // 既定適用（config最優先で level/type/planGroup を決定）
                $level     = $defaults['level'] ?? ($ev->level ?? 9);
                $type      = $defaults['type']  ?? ($ev->type ?? EventType::BACKGROUND);
                $planGroup = $defaults['plan']  ?? ($ev->planGroup ?? PlanGroup::REGULAR_PLAN);

                // FC配列化
                $arr = method_exists($ev, 'toArray') ? $ev->toArray() : get_object_vars($ev);
                $arr['extendedProps'] = $arr['extendedProps'] ?? [];
                $arr['extendedProps']['level'] = $level;

                // 決定した type/planGroup を配列にも反映（後段ロジック用）
                $arr['type']      = $type;
                $arr['planGroup'] = $planGroup;

                // === BACKGROUND は日割り展開・圧縮対象 ===
                if ($type === EventType::BACKGROUND) {
                    // 背景表示の補完
                    if (!isset($arr['display'])) {
                        $arr['display'] = 'background';
                    }

                    // クエリ範囲へクランプした日割り
                    [$S, $E] = $this->eventRange($arr, $start, $end); // [start, endExclusive]
                    $cur = $S->copy();
                    while ($cur < $E) {
                        $d = $cur->toDateString();

                        $dayEvent = $arr;
                        // 背景は日単位へ正規化（FullCalendar backgroundは日付onlyでOK）
                        $dayEvent['start'] = $d;
                        unset($dayEvent['end']);

                        $backgroundDaily[$d]['by_level'][$level][] = $dayEvent;
                        $cur->addDay();
                    }
                    continue;
                }

                // === ON は圧縮しない：元の start/end を維持して全件返す ===
                // 一応、要求範囲に一切かからないものは除外（高速化＆安全側）
                [$S, $E] = $this->eventRange($arr, $start, $end);
                // ONは範囲にかかっていればそのまま採用（start/endは元値を活かす）
                if ($E > $S) {
                    $onEvents[] = $arr;
                }
            }
        }

        // --- 背景だけ「その日の最小 level」のみ採用 ---
        $compressedBackground = [];
        foreach ($backgroundDaily as $d => $bucket) {
            if (empty($bucket['by_level'])) continue;

            $levels = array_keys($bucket['by_level']);
            sort($levels);

            // 祝日最優先（level=1）を守る
            $minLevel = ($this->rules['holiday_untouchable'] ?? true) && in_array(1, $levels, true)
                ? 1
                : $levels[0];

            foreach ($bucket['by_level'][$minLevel] as $ev) {
                $compressedBackground[] = $ev;
            }
        }

        // === 最終配列：ON（全件） + 背景（圧縮済） ===
        $out = array_merge($onEvents, $compressedBackground);

        // 並び順：extendedProps.sort_order → start → title
        usort($out, function ($a, $b) {
            $ao = (int)($a['extendedProps']['sort_order'] ?? 999);
            $bo = (int)($b['extendedProps']['sort_order'] ?? 999);
            if ($ao !== $bo) return $ao <=> $bo;

            // ISO or 'YYYY-MM-DD' 先頭10桁比較
            $as = substr((string)($a['start'] ?? ''), 0, 19);
            $bs = substr((string)($b['start'] ?? ''), 0, 19);
            if ($as !== $bs) return strcmp($as, $bs);

            return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });

        return $out;
    }

    /** @return array{0:Carbon,1:Carbon} [start, endExclusive] （クエリ範囲にクランプ）*/
    private function eventRange(array $ev, Carbon $reqStart, Carbon $reqEnd): array
    {
        // start/end は ISO or 'Y-m-d' を想定。なければ reqStart の日で補完
        $s = Carbon::parse(substr((string)($ev['start'] ?? ''), 0, 10) ?: $reqStart->toDateString())->startOfDay();
        $e = !empty($ev['end'])
            ? Carbon::parse(substr((string)$ev['end'], 0, 10))->startOfDay()
            : $s->copy()->addDay();

        // リクエスト範囲にクランプ（reqEnd は排他）
        $S = $s->lessThan($reqStart) ? $reqStart->copy()->startOfDay() : $s;
        $E = $e->greaterThan($reqEnd) ? $reqEnd->copy()->startOfDay() : $e;

        if ($E <= $S) {
            $E = $S->copy()->addDay();
        }

        return [$S, $E];
    }
}
