<?php

namespace App\Services\Calendar;

use App\Models\User;
use App\Models\Holiday;
use App\Models\UserRestPattern;
use App\Models\CompanyClosure;
use App\Models\RestPatternAdjustment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class CalendarEventService
{
    /**
     * メイン: ユーザーと期間を受け取り、最終イベント配列を返す
     * 優先度: level1(祝日) → level2(RWD) → level3(OFF) → level4(会社休暇)
     */
    public function build(User $user, Carbon $start, Carbon $end): array
    {
        // -------------- level1: Holidays (untouchable) --------------
        [$holidayEvents, $holidayDates] = $this->buildHolidays($start, $end);

        // -------------- 前処理: 割当/調整を1回だけ取得 --------------
        $assigns = UserRestPattern::with(['pattern.rules'])
            ->where('user_id', $user->id)
            ->activeBetween($start->toDateString(), $end->toDateString())
            ->orderBy('start_date')
            ->get();

        $patternIds = $assigns->pluck('rest_pattern_id')->unique()->values();
        $adjustments = RestPatternAdjustment::between($start->toDateString(), $end->toDateString())
            ->whereIn('rest_pattern_id', $patternIds)
            ->get()
            ->groupBy([
                'rest_pattern_id',
                fn($a) => $a->date->toDateString(),
            ]);

        // -------------- level2: RWD（調整出勤） --------------
        [$rwdEvents, $rwdDates] = $this->buildRwd($assigns, $adjustments, $holidayDates, $start, $end);

        // -------------- level3: OFF（LRD/ORD + 調整休日(ORD)） --------------
        [$offEvents, $offDates] = $this->buildOff($assigns, $adjustments, $holidayDates, $rwdDates, $start, $end);

        // -------------- level4: Company Closures（緑 / Fixed ALP） --------------
        // 「カバーされていない日」= level1〜3 で埋まっていない日
        $covered = $this->unionSets($holidayDates, $rwdDates, $offDates);
        $companyEvents = $this->buildCompanyClosures($covered, $start, $end);

        // 返却順は任意ですが、見栄えの安定性のため level1→2→3→4 で返します
        return collect($holidayEvents)
            ->merge($rwdEvents)
            ->merge($offEvents)
            ->merge($companyEvents)
            ->values()
            ->all();
    }

    // -----------------------------------------------------------------
    // level1: 祝日
    // -----------------------------------------------------------------
    private function buildHolidays(Carbon $start, Carbon $end): array
    {
        $events = Holiday::between($start->toDateString(), $end->toDateString())
            ->get()
            ->map(function ($h) {
                $e = (array) $h->toCalendarEvent();
                $e['display'] = 'background';
                $classes = $e['classNames'] ?? [];
                $e['classNames'] = array_unique(array_merge(is_array($classes) ? $classes : [$classes], ['fc-holiday']));
                return $e;
            });

        $dates = $events->map(fn($e) => Carbon::parse($e['start'])->toDateString())->unique()->flip();
        return [$events->all(), $dates];
    }

    // -----------------------------------------------------------------
    // level2: 調整出勤（RWD）
    // 祝日は表示しない。元々OFFの日だけRWDを立てる（work日には立てない）。
    // -----------------------------------------------------------------
    private function buildRwd(Collection $assigns, Collection $adjsGrouped, Collection $holidayDates, Carbon $start, Carbon $end): array
    {
        $events = collect();
        $dateSet = [];

        $cursor = (clone $start)->startOfDay();
        while ($cursor->lte($end)) {
            $ymd = $cursor->toDateString();

            if (isset($holidayDates[$ymd])) {
                $cursor->addDay();
                continue;
            }

            $assign = $this->findActiveAssign($assigns, $cursor);
            if ($assign && $assign->pattern) {
                $rule = $assign->pattern->rules->firstWhere('weekday', (int)$cursor->dayOfWeek);

                // 調整の取得（安全に）
                /** @var \App\Models\RestPatternAdjustment|null $adj */
                $adj = data_get($adjsGrouped, "{$assign->rest_pattern_id}.{$ymd}.0");

                if ($adj && $adj->kind === 'work_instead') {
                    // 元がOFFならRWDを立てる（=OFFを打ち消す）
                    if ($rule && $rule->kind !== 'work') {
                        $events->push([
                            'title'  => $adj->title ?: 'RWD',
                            'start'  => $ymd,
                            'allDay' => true,
                            'classNames' => ['fc-rwd'],
                            //'display' => 'background', // ←追加(背景塗りつぶしスタイル)
                            'extendedProps' => ['category' => '1_off', 'type' => 'rwd', 'code' => 'RWD'],
                        ]);
                        $dateSet[$ymd] = true; // マスク対象
                    }
                }
            }
            $cursor->addDay();
        }
        return [$events->all(), $dateSet];
    }

    // -----------------------------------------------------------------
    // level3: OFF（LRD/ORD + 調整休日(ORD)）
    // 祝日では出さない。RWD（level2）でマスクされた日は出さない。
    // -----------------------------------------------------------------
    private function buildOff(Collection $assigns, Collection $adjsGrouped, Collection $holidayDates, array $rwdDates, Carbon $start, Carbon $end): array
    {
        $eventsByDate = []; // 同一日に二重登録しないよう map で管理
        $dateSet = [];

        $cursor = (clone $start)->startOfDay();
        while ($cursor->lte($end)) {
            $ymd = $cursor->toDateString();

            if (isset($holidayDates[$ymd]) || isset($rwdDates[$ymd])) { // holidayとRWDは優先
                $cursor->addDay();
                continue;
            }

            $assign = $this->findActiveAssign($assigns, $cursor);
            if ($assign && $assign->pattern) {
                $w    = (int) $cursor->dayOfWeek;
                $rule = $assign->pattern->rules->firstWhere('weekday', $w); // work / prescribed_off / statutory_off

                /** @var \App\Models\RestPatternAdjustment|null $adj */
                $adj = data_get($adjsGrouped, "{$assign->rest_pattern_id}.{$ymd}.0");

                // 1) 調整休日（ORD追加）: 元がworkでもOFF化、所定休カラー
                if ($adj && $adj->kind === 'add_off') {
                    $eventsByDate[$ymd] = [
                        'title'  => $adj->title ?: 'ORD',
                        'start'  => $ymd,
                        'allDay' => true,
                        'classNames' => ['fc-off-prescribed'],
                        'display' => 'background', // ←追加(背景塗りつぶしスタイル)
                        'extendedProps' => ['category' => '1_off', 'type' => 'prescribed', 'code' => 'ORD'],
                    ];
                    $dateSet[$ymd] = true;
                }
                // 2) 通常OFF（調整が無い場合）
                elseif ($rule && $rule->kind !== 'work') {
                    $isStat = ($rule->kind === 'statutory_off');
                    $eventsByDate[$ymd] = [
                        'title'  => $isStat ? 'LRD' : 'ORD',
                        'start'  => $ymd,
                        'allDay' => true,
                        'classNames' => [$isStat ? 'fc-off-statutory' : 'fc-off-prescribed'],
                        'display' => 'background', // ←追加(背景塗りつぶしスタイル)
                        'extendedProps' => ['category' => '1_off', 'type' => $isStat ? 'statutory' : 'prescribed'],
                    ];
                    $dateSet[$ymd] = true;
                }
                // 3) それ以外は出さない
            }
            $cursor->addDay();
        }

        return [array_values($eventsByDate), $dateSet];
    }

    // -----------------------------------------------------------------
    // level4: 会社長期休暇（緑）
    // covered（日付セット）でカバー済みのところは出さない。
    // SB/WBは「表示される最初の5日」を Fixed ALP に。
    // -----------------------------------------------------------------
    private function buildCompanyClosures(array $covered, Carbon $start, Carbon $end): array
    {
        $events = collect();
        $closures = CompanyClosure::between($start->toDateString(), $end->toDateString())->get();

        foreach ($closures as $c) {
            $period     = CarbonPeriod::create($c->start_date, $c->end_date); // 包含
            $shownCount = 0;

            foreach ($period as $d) {
                $ymd = $d->toDateString();
                if (isset($covered[$ymd])) continue; // 既に他レイヤにカバーされている

                $title = $c->name;
                if (in_array($c->code, ['SB', 'WB'], true)) {
                    if ($shownCount < 5) {
                        $title = 'Fixed ALP';
                    }
                    $shownCount++;
                }

                $events->push([
                    'title'  => $title,
                    'start'  => $ymd,
                    'allDay' => true,
                    'classNames' => ['fc-company-break'],
                    'display' => 'background', // ←追加(背景塗りつぶしスタイル)
                    'extendedProps' => [
                        'category' => '0_company',
                        'type' => 'company_break',
                        'code' => $c->code,
                        'original_name' => $c->name,
                        'is_fixed_alp' => ($title === 'Fixed ALP'),
                        'index_in_closure' => $shownCount,
                    ],
                ]);
            }
        }
        return $events->all();
    }

    // -----------------------------------------------------------------
    // Utils
    // -----------------------------------------------------------------
    private function findActiveAssign(Collection $assigns, Carbon $date)
    {
        return $assigns->first(function ($a) use ($date) {
            return $a->start_date->lte($date) && (is_null($a->end_date) || $a->end_date->gte($date));
        });
    }

    private function unionSets(Collection|array $a, array $b, array $c): array
    {
        $set = [];
        foreach (($a instanceof Collection ? $a->keys() : array_keys($a)) as $k) {
            $set[$k] = true;
        }
        foreach ($b as $k => $v) {
            $set[$k] = true;
        }
        foreach ($c as $k => $v) {
            $set[$k] = true;
        }
        return $set;
    }
}
