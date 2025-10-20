<?php

namespace App\Http\Controllers;

use App\Models\ScheduleLine;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ScheduleLineController extends Controller
{
    public function edit(Request $request)
    {
        $activeOn   = $request->date('active_on'); // null許容
        $scheduleId = $request->integer('schedule_id') ?: null;

        $linesQuery = \App\Models\ScheduleLine::query()
            ->with(['schedule:id,label,effective_start,effective_end'])
            ->when($scheduleId, fn($q) => $q->where('schedule_id', $scheduleId))
            ->orderBy('schedule_id')->orderBy('dow')->orderBy('start_time');

        if ($activeOn) {
            $linesQuery->whereDate('effective_start', '<=', $activeOn)
                ->whereDate('effective_end', '>=', $activeOn);
        }

        $lines = $linesQuery->get();

        // ▼ 担当ユーザー（基準日）… active_on が無ければ「今日」を採用
        $baseDate = \Carbon\Carbon::parse($activeOn ?? now())->toDateString();

        $usersBySchedule = [];
        if ($lines->isNotEmpty()) {
            $scheduleIds = $lines->pluck('schedule_id')->unique()->all();

            // schedule_id ごとに、その日に有効な割当ユーザー
            $schedules = \App\Models\Schedule::with([
                'assignments' => function ($q) use ($baseDate) {
                    $q->whereDate('start_date', '<=', $baseDate)
                        ->whereDate('end_date', '>=', $baseDate)
                        ->with(['user:id,first_name,family_name,employee_code']);
                }
            ])->whereIn('id', $scheduleIds)->get();

            foreach ($schedules as $sch) {
                // 重複除去・null除外
                $usersBySchedule[$sch->id] = $sch->assignments
                    ->pluck('user')->filter()->unique('id')->values();
            }
        }

        $dowOptions = [0 => '日', 1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土'];
        $scheduleOptions = \App\Models\Schedule::orderBy('id')->get(['id', 'label']);

        return view('schedule.lineEdit', [
            'lines'           => $lines,
            'dowOptions'      => $dowOptions,
            'scheduleOptions' => $scheduleOptions,
            'activeOn'        => $activeOn,
            'scheduleId'      => $scheduleId,
            'usersBySchedule' => $usersBySchedule,
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
}
