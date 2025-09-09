<?php

namespace App\Services\Calendar\Providers;

use App\Models\User;
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
        // FullCalendar の end は排他なので -1 日を SQL 上限に使う
        $endDate   = $end->copy()->subDay()->toDateString();

        // === Sub 判定キーワード ==========================================
        $kw = (array) config('calendar_forecast.sub_keywords', ['sub', 'SUB', 'Sub']);
        $isSubName = function (?string $name) use ($kw): bool {
            if (!$name) return false;
            foreach ($kw as $w) {
                if (mb_stripos($name, $w) !== false) return true;
            }
            return false;
        };

        // === (0) 欠席ユーザー（全休のみ）を日別に収集 =====================
        // 期間が重なる approved のみ。全日: time_start/time_end が両方 NULL
        $rangeS = $start->copy()->startOfDay();
        $rangeE = $end->copy()->startOfDay(); // 排他

        $leaveRows = DB::table('leaves')
            ->select('user_id', 'start_date', 'end_date', 'time_start', 'time_end', 'status')
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $startDate);
            })
            ->get();

        /** @var array<string, array<int,bool>> $leaveUsersByDate */
        $leaveUsersByDate = [];
        foreach ($leaveRows as $lv) {
            // 全休のみ除外対象（部分休はカウント対象）
            if (!is_null($lv->time_start) || !is_null($lv->time_end)) {
                continue;
            }
            $ls = Carbon::parse($lv->start_date)->startOfDay();
            $le = $lv->end_date
                ? Carbon::parse($lv->end_date)->startOfDay()->addDay() // 排他
                : $ls->copy()->addDay();

            // 集計範囲とクランプ
            $S = $ls->greaterThan($rangeS) ? $ls : $rangeS;
            $E = $le->lessThan($rangeE) ? $le : $rangeE;
            if ($E <= $S) continue;

            $cur = $S->copy();
            while ($cur < $E) {
                $d = $cur->toDateString();
                $leaveUsersByDate[$d][(int)$lv->user_id] = true;
                $cur->addDay();
            }
        }

        // === (A) events: schedule_change & sub校名 & assigned_user_id ======
        $events = DB::table('events')
            ->select('event_date', 'source_schedule_line_id', 'assigned_user_id', 'school_name')
            ->whereBetween('event_date', [$startDate, $endDate])
            ->where('type', 'schedule_change')
            ->whereNotNull('assigned_user_id')
            ->where(function ($q) use ($kw) {
                foreach ($kw as $w) $q->orWhere('school_name', 'like', "%{$w}%");
            })
            ->get();

        $eventCountByDate = [];                 // 日別のイベント人数
        $eventUsersByDateLine = [];             // [date][line_id][user_id] = true（line重複控除用）
        foreach ($events as $e) {
            if (!$isSubName($e->school_name)) continue; // 最終ガード
            $d = Carbon::parse($e->event_date)->toDateString();
            $uid = (int)$e->assigned_user_id;

            // 欠席(全休)ユーザーはイベント由来でもカウントしない
            if (!empty($leaveUsersByDate[$d][$uid])) {
                continue;
            }

            $eventCountByDate[$d] = ($eventCountByDate[$d] ?? 0) + 1;

            if (!empty($e->source_schedule_line_id)) {
                $lid = (int)$e->source_schedule_line_id;
                $eventUsersByDateLine[$d][$lid][$uid] = true; // set
            }
        }

        // === (B) rest_pattern_adjustments: work_instead を “人数” で集計 ===
        // rpa.date 当日に rpa.rest_pattern_id がアサインされている DISTINCT user_id
        // → さらに欠席ユーザーを除外
        $adjUsersRows = DB::table('rest_pattern_adjustments as rpa')
            ->join('user_rest_patterns as urp', 'urp.rest_pattern_id', '=', 'rpa.rest_pattern_id')
            ->whereBetween('rpa.date', [$startDate, $endDate])
            ->where('rpa.kind', 'work_instead')
            ->where('rpa.is_active', true)
            // urp の適用期間に rpa.date が含まれる
            ->whereColumn('urp.start_date', '<=', 'rpa.date')
            ->where(function ($q) {
                $q->whereNull('urp.end_date')
                    ->orWhereColumn('urp.end_date', '>=', 'rpa.date');
            })
            ->select('rpa.date', 'urp.user_id')
            ->get();

        $adjCounts = []; // [date] => 人数
        $adjUsersPerDate = []; // set (distinct)
        foreach ($adjUsersRows as $row) {
            $d = Carbon::parse($row->date)->toDateString();
            $uid = (int)$row->user_id;

            // 欠席(全休)ユーザーを除外
            if (!empty($leaveUsersByDate[$d][$uid])) {
                continue;
            }

            $adjUsersPerDate[$d][$uid] = true;
        }
        foreach ($adjUsersPerDate as $d => $set) {
            $adjCounts[$d] = count($set);
        }

        // === (C) schedule_lines: sub校名 & 期間/DOW一致（“当日割当の人数”） =====
        //   1) line の schedule_id に対し、当日有効な user_schedule_assignments を集めて
        //      DISTINCT user_id を求める
        //   2) 同日同 line でイベント化された user は控除
        //   3) 欠席(全休)ユーザーは控除
        $lines = DB::table('schedule_lines')
            ->select('id', 'schedule_id', 'dow', 'school_name', 'effective_start', 'effective_end')
            ->whereDate('effective_start', '<=', $endDate)
            ->whereDate('effective_end', '>=', $startDate)
            ->where(function ($q) use ($kw) {
                foreach ($kw as $w) $q->orWhere('school_name', 'like', "%{$w}%");
            })
            ->get()
            ->filter(fn($ln) => $isSubName($ln->school_name)) // 最終ガード
            ->values();

        $scheduleIds = $lines->pluck('schedule_id')->unique()->all();
        $assignments = empty($scheduleIds)
            ? collect()
            : DB::table('user_schedule_assignments')
            ->select('user_id', 'schedule_id', 'start_date', 'end_date')
            ->whereIn('schedule_id', $scheduleIds)
            ->whereDate('start_date', '<=', $endDate)
            ->where(function ($q) use ($startDate) {
                $q->whereNull('end_date')->orWhereDate('end_date', '>=', $startDate);
            })
            ->get();

        $assignBySchedule = [];
        foreach ($assignments as $a) {
            $assignBySchedule[$a->schedule_id][] = [
                'user_id'    => (int)$a->user_id,
                'start_date' => $a->start_date,
                'end_date'   => $a->end_date, // null 可
            ];
        }

        $dbDowOf = fn(Carbon $d): int => $d->dayOfWeek; // 0=Sun..6=Sat 前提
        $lineCountByDate = [];

        foreach ($lines as $ln) {
            $effS = Carbon::parse($ln->effective_start)->startOfDay();
            $effE = Carbon::parse($ln->effective_end)->startOfDay()->addDay(); // 排他

            // line の有効期間 ∩ 集計期間
            $segS = $effS->greaterThan($rangeS) ? $effS : $rangeS;
            $segE = $effE->lessThan($rangeE) ? $effE : $rangeE;
            if ($segE <= $segS) continue;

            $as = $assignBySchedule[$ln->schedule_id] ?? [];

            $cur = $segS->copy();
            while ($cur < $segE) {
                if ($dbDowOf($cur) === (int)$ln->dow) {
                    $d = $cur->toDateString();

                    // 1) 当日有効な割当ユーザー集合
                    $users = [];
                    foreach ($as as $rec) {
                        if ($rec['start_date'] <= $d && (is_null($rec['end_date']) || $rec['end_date'] >= $d)) {
                            $users[$rec['user_id']] = true;
                        }
                    }

                    // 2) 同日同 line のイベント化ユーザーを控除
                    if (!empty($eventUsersByDateLine[$d][$ln->id])) {
                        foreach (array_keys($eventUsersByDateLine[$d][$ln->id]) as $uid) {
                            unset($users[$uid]);
                        }
                    }

                    // 3) 欠席(全休)ユーザーを控除
                    if (!empty($leaveUsersByDate[$d])) {
                        foreach (array_keys($leaveUsersByDate[$d]) as $uid) {
                            unset($users[$uid]);
                        }
                    }

                    $n = count($users);
                    if ($n > 0) {
                        $lineCountByDate[$d] = ($lineCountByDate[$d] ?? 0) + $n;
                    }
                }
                $cur->addDay();
            }
        }

        // === (D) 出力（CandidateEvent[]） ================================
        $out = [];
        $cur = $rangeS->copy();
        while ($cur < $rangeE) {
            $d = $cur->toDateString();
            $total =
                ($lineCountByDate[$d]  ?? 0) +  // line × 当日割当人数（イベント化/欠席控除後）
                ($adjCounts[$d]        ?? 0) +  // RWD 人数（欠席控除後）
                ($eventCountByDate[$d] ?? 0);   // イベント人数（欠席控除済）

            if ($total > 0) {
                $out[] = new CandidateEvent([
                    'title' => 'Sub ' . $total,
                    'start' => $d,
                    'allDay' => true,
                    'classNames' => ['ev-subcount'],
                    'extendedProps' => [
                        'category' => 'subcount',
                        'count'    => $total,
                    ],
                ]);
            }
            $cur->addDay();
        }

        return $out;
    }
}
