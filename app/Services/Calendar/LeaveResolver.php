<?php

namespace App\Services\Calendar;

use App\Models\User;
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class LeaveResolver
{
    /** @var CalendarEventProvider[] */
    private array $providers = [];
    private array $meta;
    private array $rules;

    public function __construct(iterable $providers)
    {
        $this->providers = is_array($providers) ? $providers : iterator_to_array($providers);
        $this->meta  = config('calendar_leave.providers', []);
        $this->rules = config('calendar_leave.rules', []);
    }

    /**
     * 背景(BACKGROUND)は日割り→日別最小levelのみ採用（祝日優先）
     * ON(Event/Leave)は圧縮せず全件返す（背景に「足す」）
     */
    public function build(?User $user, Carbon $start, Carbon $end): array
    {
        $backgroundDaily = []; // 'YYYY-MM-DD' => ['by_level' => [level => [event,array...]]]
        $onEvents = [];

        foreach ($this->providers as $p) {
            $cls = get_class($p);
            $defaults = $this->meta[$cls] ?? [];

            foreach ($p->provide($user, $start, $end) as $ev) {
                if (is_array($ev)) $ev = (object)$ev;

                // config を最優先に level/type/plan を決定
                $level     = $defaults['level'] ?? ($ev->level ?? 9);
                $type      = $defaults['type']  ?? ($ev->type ?? EventType::BACKGROUND);
                $planGroup = $defaults['plan']  ?? ($ev->planGroup ?? PlanGroup::REGULAR_PLAN);

                // FC配列化
                $arr = method_exists($ev, 'toArray') ? $ev->toArray() : get_object_vars($ev);
                $arr['extendedProps'] = $arr['extendedProps'] ?? [];
                $arr['extendedProps']['level'] = $level;

                // 決定値を直に埋めて後段の判定で使う
                $arr['type']      = $type;
                $arr['planGroup'] = $planGroup;

                // ★id を保証（toArray 等で落ちた場合に extendedProps.leave.id から復元）
                if (empty($arr['id']) && !empty($arr['extendedProps']['leave']['id'])) {
                    $arr['id'] = (string) $arr['extendedProps']['leave']['id'];
                }

                if ($type === EventType::BACKGROUND) {
                    // 背景表示の補完
                    if (!isset($arr['display'])) $arr['display'] = 'background';

                    // 日割り展開（クエリ範囲にクランプ）
                    [$S, $E] = $this->eventRange($arr, $start, $end); // endExclusive
                    $cur = $S->copy();
                    while ($cur < $E) {
                        $d = $cur->toDateString();

                        $dayEvent = $arr;
                        $dayEvent['start'] = $d; // 背景は日付のみでOK
                        unset($dayEvent['end']);

                        $backgroundDaily[$d]['by_level'][$level][] = $dayEvent;
                        $cur->addDay();
                    }
                    continue;
                }

                // === ON はそのまま素通し（圧縮しない）===
                [$S, $E] = $this->eventRange($arr, $start, $end);
                if ($E > $S) {
                    $onEvents[] = $arr;
                }
            }
        }

        // --- 背景は「その日の最小 level」を採用（祝日 untouchable） ---
        $compressedBackground = [];
        foreach ($backgroundDaily as $d => $bucket) {
            if (empty($bucket['by_level'])) continue;

            $levels = array_keys($bucket['by_level']);
            sort($levels);

            $minLevel = ($this->rules['holiday_untouchable'] ?? true) && in_array(1, $levels, true)
                ? 1
                : $levels[0];

            foreach ($bucket['by_level'][$minLevel] as $ev) {
                $compressedBackground[] = $ev;
            }
        }

        // === 最終出力 ===
        // on_adds_to_background = true の前提で単純結合
        $out = array_merge($onEvents, $compressedBackground);

        // 並び：sort_order → start → title
        usort($out, function ($a, $b) {
            $ao = (int)($a['extendedProps']['sort_order'] ?? 999);
            $bo = (int)($b['extendedProps']['sort_order'] ?? 999);
            if ($ao !== $bo) return $ao <=> $bo;

            $as = substr((string)($a['start'] ?? ''), 0, 19);
            $bs = substr((string)($b['start'] ?? ''), 0, 19);
            if ($as !== $bs) return strcmp($as, $bs);

            return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        });

        return $out;
    }

    /**
     * @return array{0:Carbon,1:Carbon} [start, endExclusive]
     * FullCalendarの allDay 仕様や 'YYYY-MM-DD' / ISO 文字列を考慮しつつ
     * リクエスト範囲にクランプ
     */
    private function eventRange(array $ev, Carbon $reqStart, Carbon $reqEnd): array
    {
        $s = Carbon::parse(substr((string)($ev['start'] ?? ''), 0, 10) ?: $reqStart->toDateString())->startOfDay();
        $e = !empty($ev['end'])
            ? Carbon::parse(substr((string)$ev['end'], 0, 10))->startOfDay()
            : $s->copy()->addDay();

        $S = $s->lessThan($reqStart) ? $reqStart->copy()->startOfDay() : $s;
        $E = $e->greaterThan($reqEnd) ? $reqEnd->copy()->startOfDay() : $e;

        if ($E <= $S) $E = $S->copy()->addDay();

        return [$S, $E];
    }
}
