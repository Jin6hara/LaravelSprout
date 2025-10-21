<?php

namespace App\Http\Controllers;

use App\Models\ScheduleLine;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ScheduleLineController extends Controller
{
    /**
     * ScheduleLine 編集 + 閲覧（詳細は時間系列でグループ化）
     */
    public function edit(Request $request)
    {
        // フィルタ
        $activeOn   = $request->input('active_on');                 // Y-m-d or null
        $scheduleId = $request->integer('schedule_id') ?: null;

        // ScheduleLine 本体 + 関連ロード
        $linesQuery = ScheduleLine::query()
            ->with([
                'schedule:id,label,effective_start,effective_end',
                // details（開始時刻/レッスン情報を一緒に）
                'details' => function ($q) {
                    $q->with([
                        'start:id,start_time', // lesson_start_times
                        'lesson:id,lesson_name,lesson_code,lesson_minute,lesson_type',
                    ])->orderBy('lesson_start_time_id');
                },
            ])
            ->when($scheduleId, fn($q) => $q->where('schedule_id', $scheduleId))
            ->orderBy('schedule_id')
            ->orderBy('dow')
            ->orderBy('start_time');

        // 有効日フィルタ（line 自体の有効期間で絞る）
        if (!empty($activeOn)) {
            $linesQuery->whereDate('effective_start', '<=', $activeOn)
                ->whereDate('effective_end', '>=', $activeOn);
        }

        $lines = $linesQuery->get();

        // 担当ユーザー（active_on が未指定なら今日を基準）
        $baseDate = Carbon::parse($activeOn ?? now())->toDateString();

        $usersBySchedule = [];
        if ($lines->isNotEmpty()) {
            $scheduleIds = $lines->pluck('schedule_id')->unique()->values()->all();

            // Schedule に紐づく当日有効な割当（assignments）をロード
            $schedules = Schedule::query()
                ->with([
                    'assignments' => function ($q) use ($baseDate) {
                        $q->whereDate('start_date', '<=', $baseDate)
                            ->whereDate('end_date', '>=', $baseDate)
                            ->with(['user:id,first_name,family_name,employee_code']);
                    },
                ])
                ->whereIn('id', $scheduleIds)
                ->get(['id']);

            foreach ($schedules as $sch) {
                $usersBySchedule[$sch->id] = $sch->assignments
                    ->pluck('user')
                    ->filter()
                    ->unique('id')
                    ->values();
            }
        }

        // details を「期間の変化点」で区切った時間系列へ整形
        $seriesByLine = [];
        foreach ($lines as $line) {
            $seriesByLine[$line->id] = $this->buildTimeSeries($line->details);
        }

        // セレクト等
        $dowOptions = [
            0 => '日',
            1 => '月',
            2 => '火',
            3 => '水',
            4 => '木',
            5 => '金',
            6 => '土',
        ];
        $scheduleOptions = Schedule::orderBy('id')->get(['id', 'label']);

        return view('schedule.lineEdit', [
            'lines'           => $lines,
            'dowOptions'      => $dowOptions,
            'scheduleOptions' => $scheduleOptions,
            'activeOn'        => $activeOn,
            'scheduleId'      => $scheduleId,
            'usersBySchedule' => $usersBySchedule,
            'seriesByLine'    => $seriesByLine,
        ]);
    }

    public function update(Request $request, ScheduleLine $line)
    {
        // バリデーション
        $data = $request->validate([
            'dow'             => ['required', 'integer', Rule::in([0, 1, 2, 3, 4, 5, 6])],
            'school_name'     => ['required', 'string', 'max:255'],
            'start_time'      => ['required', 'date_format:H:i'],
            'end_time'        => ['required', 'date_format:H:i', function ($attr, $val, $fail) use ($request) {
                if ($request->input('start_time') && $val <= $request->input('start_time')) {
                    $fail('end_time は start_time より後である必要があります。');
                }
            }],
            'effective_start' => ['required', 'date'],
            'effective_end'   => ['required', 'date', function ($attr, $val, $fail) use ($request) {
                if ($request->input('effective_start') && $val < $request->input('effective_start')) {
                    $fail('effective_end は effective_start 以降である必要があります。');
                }
            }],
        ]);

        $line->fill($data)->save();

        return back()->with('status', "Line #{$line->id} を更新しました。");
    }

    /**
     * schedule_details を内容の変化点で分割して「時間系列」へ整形
     *
     * @param  \Illuminate\Support\Collection  $details  // of ScheduleDetail (with ->start, ->lesson)
     * @return array<int, array{start:?Carbon, end:?Carbon, items:array<int,array{time:string,name:?string,code:?string,minute:?int,type:?string}>}>
     */
    private function buildTimeSeries(Collection $details): array
    {
        if ($details->isEmpty()) {
            return [];
        }

        // 1) 変化点候補（start, end+1日）を収集
        $points = collect();
        $maxEnd = null;
        $hasOpenEnd = false;

        foreach ($details as $d) {
            $s = Carbon::parse($d->effective_start)->startOfDay();
            $points->push($s->copy());

            if ($d->effective_end) {
                $e = Carbon::parse($d->effective_end)->startOfDay();
                $points->push($e->copy()->addDay()); // 終了日の翌日を区切り点に
                $maxEnd = $maxEnd ? $e->max($maxEnd) : $e;
            } else {
                $hasOpenEnd = true; // オープンエンドあり
            }
        }

        $points = $points
            ->unique(fn($c) => $c->toDateString())
            ->sort()
            ->values();

        if ($points->isEmpty()) {
            return [];
        }

        // 2) 区間生成：[points[i], points[i+1]-1]、末尾は open もしくは maxEnd
        $segments = [];
        for ($i = 0; $i < $points->count(); $i++) {
            $start = $points[$i]->copy();
            if ($i + 1 < $points->count()) {
                $end = $points[$i + 1]->copy()->subDay();
            } else {
                $end = $hasOpenEnd ? null : $maxEnd;
            }

            // 3) 区間に有効な details を抽出（重なり判定）
            $active = $details->filter(function ($d) use ($start, $end) {
                $ds = Carbon::parse($d->effective_start)->startOfDay();
                $de = $d->effective_end ? Carbon::parse($d->effective_end)->startOfDay() : null;

                // 重なり: [ds, de(または∞)] と [start, end(または∞)] が交差
                $leftOk  = $de ? $de >= $start : true;      // detail の終端が start 以降
                $rightOk = $end ? $ds <= $end : true;       // detail の始端が end 以前
                return $leftOk && $rightOk;
            });

            if ($active->isEmpty()) {
                continue;
            }

            // 4) 表示アイテム（開始時刻順）
            $items = $active->sortBy(function ($d) {
                return optional($d->start)->start_time ?? '99:99:99';
            })
                ->map(function ($d) {
                    // 1) start_time を多様な入力から "HH:MM" に正規化
                    $raw = optional($d->start)->start_time; // 文字列/Carbon/null の可能性
                    $startStr = null;

                    if ($raw instanceof \DateTimeInterface) {
                        $startStr = \Carbon\Carbon::instance($raw)->format('H:i');
                    } else if ($raw !== null) {
                        $s = trim((string)$raw);

                        // 全角コロン対策
                        $s = str_replace('：', ':', $s);

                        if (preg_match('/^\d{1,2}:\d{2}$/', $s)) {
                            // 09:00
                            $startStr = $s;
                        } elseif (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $s)) {
                            // 09:00:00 -> 09:00
                            $startStr = substr($s, 0, 5);
                        } elseif (preg_match('/^\d{3,4}$/', $s)) {
                            // 900 / 0900 -> 09:00
                            $s = str_pad($s, 4, '0', STR_PAD_LEFT);
                            $startStr = substr($s, 0, 2) . ':' . substr($s, 2, 2);
                        } else {
                            // どうしても不明なら Carbon::parse に賭けて H:i に整形（失敗は無視）
                            try {
                                $startStr = \Carbon\Carbon::parse($s)->format('H:i');
                            } catch (\Throwable $e) {
                                $startStr = null;
                            }
                        }
                    }

                    // 2) lesson_minute を取得
                    $lesson = $d->lesson;
                    $minute = $lesson->lesson_minute ?? null;
                    $minute = is_numeric($minute) ? (int)$minute : null;

                    // 3) 終了時刻 = start + minute （両方そろっている時だけ算出）
                    $endStr = null;
                    if ($startStr && $minute !== null) {
                        $endStr = \Carbon\Carbon::createFromFormat('H:i', $startStr)
                            ->addMinutes($minute)
                            ->format('H:i');
                    }

                    return [
                        'name'   => $lesson->lesson_name ?? null,
                        'code'   => $lesson->lesson_code ?? null,
                        'minute' => $minute,
                        'start'  => $startStr,   // "HH:MM" or null
                        'end'    => $endStr,     // "HH:MM" or null
                    ];
                })
                ->values()
                ->all();

            $segments[] = [
                'start' => $start,
                'end'   => $end,
                'items' => $items,
            ];
        }

        return $segments;
    }
}
