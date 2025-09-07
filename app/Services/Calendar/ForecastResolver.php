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

    public function __construct(iterable $providers)
    {
        $this->providers = is_array($providers) ? $providers : iterator_to_array($providers);
        $this->meta = config('calendar.providers', []);
    }

    public function build(?User $user, Carbon $start, Carbon $end): array
    {
        $holidays = [];
        $closures = [];

        // 1) 収集 + 正規化（provider名を埋めておく）
        foreach ($this->providers as $p) {
            $cls = get_class($p);
            $defaults = $this->meta[$cls] ?? [];

            foreach ($p->provide($user, $start, $end) as $ev) {
                if (is_array($ev)) $ev = (object)$ev;

                $ev->level     = $defaults['level'] ?? ($ev->level ?? 9);
                $ev->type      = $ev->type ?? ($defaults['type'] ?? EventType::BACKGROUND);
                $ev->planGroup = $defaults['plan'] ?? ($ev->planGroup ?? PlanGroup::REGULAR_PLAN);

                // FC配列へ
                $arr = method_exists($ev, 'toArray') ? $ev->toArray() : get_object_vars($ev);

                // 背景指定の補完
                if (!isset($arr['display']) && ($ev->type === EventType::BACKGROUND)) {
                    $arr['display'] = 'background';
                }
                $arr['allDay'] = $arr['allDay'] ?? true;

                // meta補完
                $arr['extendedProps'] = $arr['extendedProps'] ?? [];
                $arr['extendedProps']['level'] = $arr['extendedProps']['level'] ?? $ev->level;
                $arr['extendedProps']['_provider'] = $arr['extendedProps']['_provider']
                    ?? class_basename($cls); // "HolidayProvider" / "ClosureProvider" など

                // 振り分け
                $prov = $arr['extendedProps']['_provider'];
                if (stripos($prov, 'Holiday') !== false) {
                    $holidays[] = $arr;
                } elseif (stripos($prov, 'Closure') !== false) {
                    $closures[] = $arr;
                } else {
                    // 念のため：他が混入しても無視せず返したければここで扱う
                }
            }
        }

        // 2) 祝日の日付セットを作る (endは排他的)
        $holidayDates = [];
        foreach ($holidays as $h) {
            [$hs, $he] = $this->eventRange($h);
            $d = $hs->copy();
            while ($d < $he) {
                $holidayDates[$d->toDateString()] = true;
                $d->addDay();
            }
        }

        // 3) 会社休暇から「祝日の当日」を切り抜く（分割）
        $closureSegments = [];
        foreach ($closures as $c) {
            [$cs, $ce] = $this->eventRange($c); // [start, endExclusive]

            // 単日 or 期間
            $segments = $this->splitRangeExcludingDates($cs, $ce, $holidayDates);
            if (empty($segments)) {
                // 全日が祝日で埋まっているなら何も出さない
                continue;
            }
            foreach ($segments as [$ss, $se]) {
                $seg = $c;
                $seg['start'] = $ss->toDateString();
                $seg['end']   = $se->toDateString(); // FC allDay の end は排他的
                $closureSegments[] = $seg;
            }
        }

        // 4) 祝日 + 分割済み会社休暇 を統合・安定ソート
        $events = array_merge($holidays, $closureSegments);
        usort($events, function ($a, $b) {
            $al = $a['extendedProps']['level'] ?? 9;
            $bl = $b['extendedProps']['level'] ?? 9;
            if ($al !== $bl) return $al <=> $bl;

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

    /** @return array{0:Carbon,1:Carbon} [start, endExclusive] */
    private function eventRange(array $ev): array
    {
        $s = Carbon::parse(substr(($ev['start'] ?? ''), 0, 10) ?: now()->toDateString())->startOfDay();
        // end が無ければ単日 → endExclusive = s + 1day
        $e = !empty($ev['end'])
            ? Carbon::parse(substr($ev['end'], 0, 10))->startOfDay()
            : $s->copy()->addDay();
        return [$s, $e];
    }

    /**
     * 指定の [start, endExclusive) から、blacklist（日付文字列）の日を除外し、
     * 連続区間ごとの [segStart, segEndExclusive] を返す
     * @param array<string,bool> $blacklist
     * @return array{0:Carbon,1:Carbon}[]
     */
    private function splitRangeExcludingDates(Carbon $start, Carbon $endExclusive, array $blacklist): array
    {
        $segments = [];
        $cursor = $start->copy();
        $segStart = null;

        while ($cursor < $endExclusive) {
            $day = $cursor->toDateString();
            $isBlocked = isset($blacklist[$day]);

            if ($isBlocked) {
                if ($segStart) {
                    // 直前までを区切る
                    $segments[] = [$segStart, $cursor->copy()];
                    $segStart = null;
                }
            } else {
                if (!$segStart) $segStart = $cursor->copy();
            }
            $cursor->addDay();
        }
        if ($segStart) {
            $segments[] = [$segStart, $endExclusive->copy()];
        }
        return $segments;
    }
}
