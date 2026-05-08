<?php

namespace App\Services\Calendar\Providers;

use App\Models\User;
use App\Services\CurrentScopeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\Calendar\Contracts\CalendarEventProvider;
use App\Services\Calendar\CandidateEvent;

class SubCountProvider implements CalendarEventProvider
{
    /** @return array<CandidateEvent> */
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $startDate = $start->toDateString();
        $endDate   = $end->copy()->subDay()->toDateString(); // FC end は排他

        // 現在の管理スコープ内ユーザーのみ対象にする
        $targetUserIds = app(CurrentScopeService::class)->targetUserIds();

        // === Sub 判定（校名に含まれる語） ===
        $kw = (array) config('calendar_forecast.sub_keywords', ['sub', 'SUB', 'Sub', 'サブ', '代行']);
        $isSubName = function (?string $name) use ($kw): bool {
            if (!$name) return false;
            foreach ($kw as $w) if (mb_stripos($name, $w) !== false) return true;
            return false;
        };

        // === 欠席(全休)ユーザー 日別集合 ===
        $rangeS = $start->copy()->startOfDay();
        $rangeE = $end->copy()->startOfDay(); // 排他
        $leaveRows = DB::table('leaves')
            ->select('user_id', 'start_date', 'end_date', 'time_start', 'time_end', 'status')
            ->whereIn('user_id', $targetUserIds)
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('start_date', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $startDate);
            })
            ->get();

        $leaveUsersByDate = []; // [date][user_id] = true
        foreach ($leaveRows as $lv) {
            if (!is_null($lv->time_start) || !is_null($lv->time_end)) continue; // 全休のみ除外対象
            $ls = Carbon::parse($lv->start_date)->startOfDay();
            $le = $lv->end_date ? Carbon::parse($lv->end_date)->startOfDay()->addDay() : $ls->copy()->addDay();
            $S = $ls->greaterThan($rangeS) ? $ls : $rangeS;
            $E = $le->lessThan($rangeE) ? $le : $rangeE;
            if ($E <= $S) continue;
            for ($d = $S->copy(); $d < $E; $d->addDay()) {
                $leaveUsersByDate[$d->toDateString()][(int)$lv->user_id] = true;
            }
        }

        // === (B) rest_pattern_adjustments: work_instead を人数で ===
        $adjRows = DB::table('rest_pattern_adjustments as rpa')
            ->join('user_rest_patterns as urp', 'urp.rest_pattern_id', '=', 'rpa.rest_pattern_id')
            ->whereBetween('rpa.date', [$startDate, $endDate])
            ->where('rpa.kind', 'work_instead')
            ->where('rpa.is_active', true)
            ->whereColumn('urp.start_date', '<=', 'rpa.date')
            ->where(function ($q) {
                $q->whereNull('urp.end_date')->orWhereColumn('urp.end_date', '>=', 'rpa.date');
            })
            ->whereIn('urp.user_id', $targetUserIds)
            ->select('rpa.date', 'urp.user_id')
            ->get();

        $rwdUsersCounted = []; // [date][user_id]=true
        $rwdUsersAbsent  = []; // [date][user_id]=true
        foreach ($adjRows as $row) {
            $d   = Carbon::parse($row->date)->toDateString();
            $uid = (int)$row->user_id;
            if (!empty($leaveUsersByDate[$d][$uid])) $rwdUsersAbsent[$d][$uid] = true;
            else                                    $rwdUsersCounted[$d][$uid] = true;
        }

        // === (C) schedule_lines: 当日割当ユーザー数（欠席控除） ===
        $lines = DB::table('schedule_lines')
            ->select('id', 'schedule_id', 'dow', 'school_name', 'effective_start', 'effective_end')
            ->whereDate('effective_start', '<=', $endDate)
            ->whereDate('effective_end', '>=', $startDate)
            ->where(function ($q) use ($kw) {
                foreach ($kw as $w) {
                    $q->orWhereRaw(
                        "LOWER(COALESCE(school_name, '')) LIKE ?",
                        ['%' . mb_strtolower($w, 'UTF-8') . '%']
                    );
                }
            })
            ->get()
            ->filter(fn($ln) => $isSubName($ln->school_name))
            ->values();

        $scheduleIds = $lines->pluck('schedule_id')->unique()->all();
        $assignments = empty($scheduleIds) ? collect() :
            DB::table('schedules')
            ->select([
                'user_id',
                DB::raw('id as schedule_id'),
                DB::raw('effective_start as start_date'),
                DB::raw('effective_end as end_date'),
            ])
            ->whereIn('id', $scheduleIds)
            ->whereIn('user_id', $targetUserIds)
            ->whereDate('effective_start', '<=', $endDate)
            ->whereDate('effective_end', '>=', $startDate)
            ->get();

        $assignBySchedule = [];
        foreach ($assignments as $a) {
            $assignBySchedule[$a->schedule_id][] = [
                'user_id'    => (int)$a->user_id,
                'start_date' => $a->start_date,
                'end_date'   => $a->end_date, // null 可
            ];
        }

        $dbDowOf = fn(Carbon $d): int => $d->dayOfWeek; // 0..6 (Sun..Sat)
        $lineUsersCounted = []; // [date][user_id]=true
        $lineUsersAbsent  = []; // [date][user_id]=true

        $rangeS = $start->copy()->startOfDay();
        $rangeE = $end->copy()->startOfDay(); // 排他

        foreach ($lines as $ln) {
            $effS = Carbon::parse($ln->effective_start)->startOfDay();
            $effE = Carbon::parse($ln->effective_end)->startOfDay()->addDay(); // 排他

            $segS = $effS->greaterThan($rangeS) ? $effS : $rangeS;
            $segE = $effE->lessThan($rangeE) ? $effE : $rangeE;
            if ($segE <= $segS) continue;

            $as = $assignBySchedule[$ln->schedule_id] ?? [];

            for ($cur = $segS->copy(); $cur < $segE; $cur->addDay()) {
                if ($dbDowOf($cur) !== (int)$ln->dow) continue;
                $d = $cur->toDateString();

                // 当日有効な割当ユーザー set
                $users = [];
                foreach ($as as $rec) {
                    if ($rec['start_date'] <= $d && (is_null($rec['end_date']) || $rec['end_date'] >= $d)) {
                        $users[$rec['user_id']] = true;
                    }
                }

                foreach (array_keys($users) as $uid) {
                    if (!empty($leaveUsersByDate[$d][$uid])) $lineUsersAbsent[$d][$uid] = true;
                    else                                    $lineUsersCounted[$d][$uid] = true;
                }
            }
        }

        // === (D) subs: 直接登録された代行（日付ベース、欠席控除） ===
        $subsRows = DB::table('subs')
            ->select('user_id', 'sub_date', 'start_time', 'end_time') // ← 時間も取得（モーダル用）
            ->whereIn('user_id', $targetUserIds)
            ->whereBetween('sub_date', [$startDate, $endDate])
            ->get();

        $subsUsersCounted = []; // [date][user_id]=array{start_hm?,end_hm?} | true
        $subsUsersAbsent  = []; // [date][user_id]=array{start_hm?,end_hm?} | true
        foreach ($subsRows as $row) {
            $d   = Carbon::parse($row->sub_date)->toDateString();
            $uid = (int)$row->user_id;

            // DBの TIME(= 'HH:MM:SS' など) を 'H:i' に正規化（null対応）
            $fmt = function ($t) {
                if ($t === null) return null;
                // 文字列 'HH:MM:SS' の想定だが、Carbon::parse で柔軟に
                try {
                    return Carbon::parse($t)->format('H:i');
                } catch (\Throwable $e) {
                    return null;
                }
            };
            $startHm = $fmt($row->start_time);
            $endHm   = $fmt($row->end_time);

            // 欠席なら absent 側、そうでなければ counted 側へ
            $payload = array_filter(['start_hm' => $startHm, 'end_hm' => $endHm], fn($v) => $v !== null);

            if (!empty($leaveUsersByDate[$d][$uid])) {
                // start/end が取れなければ true だけでもOK（JS側は自動で時間非表示）
                $subsUsersAbsent[$d][$uid] = !empty($payload) ? $payload : true;
            } else {
                $subsUsersCounted[$d][$uid] = !empty($payload) ? $payload : true;
            }
        }

        // === ユーザー名解決（まとめて 1 クエリ） ===
        $allIds = [];
        $collect = function (&$arr) use (&$allIds) {
            foreach ($arr as $d => $set) foreach (array_keys($set) as $uid) $allIds[$uid] = true;
        };
        $collect($rwdUsersCounted);
        $collect($rwdUsersAbsent);
        $collect($lineUsersCounted);
        $collect($lineUsersAbsent);
        $collect($subsUsersCounted);   // ★ subs_table からの出席Sub数
        $collect($subsUsersAbsent);    // ★ subs_table からの欠席Sub数

        $nameById = empty($allIds) ? [] :
            DB::table('users')->whereIn('id', array_keys($allIds))->pluck('name', 'id')->map(fn($n) => (string)$n)->toArray();

        // === 出力（B+C+D） ===
        $out = [];
        for ($d = $rangeS->copy(); $d < $rangeE; $d->addDay()) {
            $day = $d->toDateString();

            $count_line  = isset($lineUsersCounted[$day])  ? count($lineUsersCounted[$day])  : 0;
            $count_rwd   = isset($rwdUsersCounted[$day])   ? count($rwdUsersCounted[$day])   : 0;
            $count_subs  = isset($subsUsersCounted[$day])  ? count($subsUsersCounted[$day])  : 0; // ★ subs_table からの出席数
            $total       = $count_line + $count_rwd + $count_subs;

            $abs_line    = isset($lineUsersAbsent[$day])  ? count($lineUsersAbsent[$day])  : 0;
            $abs_rwd     = isset($rwdUsersAbsent[$day])   ? count($rwdUsersAbsent[$day])   : 0;
            $abs_subs    = isset($subsUsersAbsent[$day])  ? count($subsUsersAbsent[$day])  : 0;   // ★ subs_table からの欠席数
            $totalCandidates = $total + $abs_line + $abs_rwd + $abs_subs;

            if ($totalCandidates === 0) continue;

            // ★ subs の場合、値が true のこともあれば ['start_hm'=>..,'end_hm'=>..] のこともあるので両対応
            $makeList = function ($set) use ($nameById) {
                $arr = [];
                foreach (($set ?? []) as $uid => $val) {
                    $item = [
                        'id'   => (int)$uid,
                        'name' => $nameById[$uid] ?? ('#' . $uid),
                    ];
                    if (is_array($val)) {
                        if (!empty($val['start_hm'])) $item['start_hm'] = $val['start_hm'];
                        if (!empty($val['end_hm']))   $item['end_hm']   = $val['end_hm'];
                    }
                    $arr[] = $item;
                }
                // 名前で安定ソート
                usort($arr, fn($a, $b) => strcmp($a['name'], $b['name']));
                return $arr;
            };

            $users = [
                'line'         => $makeList($lineUsersCounted[$day]  ?? []),
                'work_instead' => $makeList($rwdUsersCounted[$day]   ?? []),
                'subs'         => $makeList($subsUsersCounted[$day]  ?? []), // ★時間付きに対応
            ];
            $absent_users = [
                'line'         => $makeList($lineUsersAbsent[$day]  ?? []),
                'work_instead' => $makeList($rwdUsersAbsent[$day]   ?? []),
                'subs'         => $makeList($subsUsersAbsent[$day]  ?? []),  // ★時間付きに対応
            ];

            $out[] = new CandidateEvent([
                'title' => 'Sub ' . $total,
                'start' => $day,
                'allDay' => true,
                'classNames' => array_filter([
                    'ev-subcount',
                    $total === 0 ? 'ev-subcount-zero' : null,
                ]),
                'extendedProps' => [
                    'category' => 'subcount',
                    'count' => $total,
                    'count_breakdown' => [
                        'line'         => $count_line,
                        'work_instead' => $count_rwd,
                        'subs'         => $count_subs,   // ★ 追加
                    ],
                    'users'         => $users,
                    'absent_users'  => $absent_users,
                ],
            ]);
        }

        return $out;
    }
}
