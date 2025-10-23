<?php

namespace App\Http\Controllers;

use App\Models\ScheduleLine;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ScheduleLineController extends Controller
{
    public function edit(Request $request)
    {
        // フィルタ
        $activeOn   = $request->input('active_on');                 // Y-m-d or null

        // 「Not Assigned」は 'null' 文字列で送られる想定
        $scheduleIdRaw = $request->input('schedule_id');            // '', 'null', '12' など
        $scheduleId    = $scheduleIdRaw;

        // ScheduleLine 本体 + 関連ロード
        $linesQuery = ScheduleLine::query()
            ->with([
                'schedule:id,label,effective_start,effective_end,user_id',
                // ▼ 追加: schedule 所有ユーザーを同時ロード
                'schedule.user:id,first_name,family_name,employee_code',
                // details（開始時刻/レッスン情報を一緒に）
                'details' => function ($q) {
                    $q->with([
                        'start:id,start_time', // lesson_start_times
                        'lesson:id,lesson_name,lesson_code,lesson_minute,lesson_type',
                    ])->orderBy('lesson_start_time_id');
                },
            ])
            ->orderBy('schedule_id')
            ->orderBy('dow')
            ->orderBy('start_time');

        // ▼ schedule_id フィルタ（'null' は未割当のみ、数値はそのID、空はすべて）
        if ($scheduleIdRaw === 'null') {
            $linesQuery->whereNull('schedule_id');
        } elseif (is_numeric($scheduleIdRaw) && $scheduleIdRaw !== '') {
            $linesQuery->where('schedule_id', (int)$scheduleIdRaw);
        }

        // ▼ Schedule 所有ユーザーを取得（user_id 経由）
        $scheduleOptions = Schedule::query()
            ->with(['user:id,first_name,family_name,employee_code'])
            ->get()
            ->map(function ($s) {
                $u = $s->user;
                if ($u) {
                    // 表示を「family first [code]」に
                    $label = trim($u->family_name . ' ' . ($u->first_name ?? ''));
                    if (!empty($u->employee_code)) {
                        $label .= " [{$u->employee_code}]";
                    }
                } else {
                    // ユーザーが紐づいていない場合
                    $label = "(未割当) Schedule #{$s->id}";
                }

                return [
                    'id'    => $s->id,
                    'label' => $label,
                ];
            })
            ->sortBy('label')
            ->values();

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

            // assignments を使わず、Schedule に直付けされた user を取得
            $schedules = Schedule::query()
                ->with(['user:id,first_name,family_name,employee_code'])
                ->whereIn('id', $scheduleIds)
                ->get(['id', 'user_id']);

            foreach ($schedules as $sch) {
                $u = $sch->user;
                // 旧来の chips 互換のため Collection を渡す（0 or 1件）
                $usersBySchedule[$sch->id] = collect($u ? [$u] : [])->values();
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
        // ▼ 重複定義を避けるため、既に上で生成した $scheduleOptions をそのまま利用
        // $scheduleOptions = Schedule::orderBy('id')->get(['id', 'label']);

        return view('schedule.lineEdit', [
            'lines'           => $lines,
            'dowOptions'      => $dowOptions,
            'scheduleOptions' => $scheduleOptions,
            'activeOn'        => $activeOn,
            'scheduleId'      => $scheduleId,
            'usersBySchedule' => $usersBySchedule,
            'seriesByLine'    => $seriesByLine,
            // 'scheduleOptions' => $scheduleOptions, // ▼ 重複キー削除
        ]);
    }

    public function update(Request $request, ScheduleLine $line)
    {
        // バリデーション
        $data = $request->validate([
            'schedule_id'     => ['nullable', 'exists:schedules,id'], //'required',
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
     * ScheduleLine を削除（JSON）
     */
    public function destroy(Request $request, ScheduleLine $line): JsonResponse
    {
        // （必要なら）ポリシー等
        // $this->authorize('delete', $line);

        try {
            // 参照整合: details が外部キー制約の場合は先に削除
            // （migrations で cascadeOnDelete なら不要だが安全側で）
            if (method_exists($line, 'details')) {
                $line->details()->delete();
            }

            $id = $line->id;
            $line->delete();

            return response()->json([
                'ok'      => true,
                'message' => "Line #{$id} を削除しました。",
                'id'      => $id,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok'      => false,
                'message' => '削除に失敗しました。関連データや制約をご確認ください。',
                'error'   => $e->getMessage(),
            ], 422);
        }
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
