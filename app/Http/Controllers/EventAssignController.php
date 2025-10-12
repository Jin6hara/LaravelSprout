<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Services\Calendar\ForecastResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventAssignController extends Controller
{
    public function edit(Request $request, ForecastResolver $resolver)
    {
        $viewUser = $request->user();
        // 対象月（YYYY-MM）。指定なければ当月
        $ym = $request->query('month', now()->format('Y-m'));
        [$y,$m] = explode('-', $ym);
        $start = Carbon::createFromDate((int)$y,(int)$m,1)->startOfDay();
        $end   = $start->copy()->addMonth()->startOfDay(); // [start, end)

        // --- ForecastResolver から Summary 日次集計を作成 ---
        $events = $resolver->build($viewUser, $start, $end);

        // 日付キー初期化
        $summary = [];
        $cur = $start->copy();
        while ($cur < $end) {
            $d = $cur->toDateString();
            $summary[$d] = ['date'=>$d,'weekday'=>'','subs_total'=>0,'events_total'=>0];
            $cur->addDay();
        }

        foreach ($events as $e) {
            $type = $e['type'] ?? 'ON'; // BACKGROUND or ON
            $d    = substr((string)($e['start'] ?? ''), 0, 10);
            if (!isset($summary[$d])) continue;

            if (($e['extendedProps']['category'] ?? null) === 'subcount') {
                // SubCountProvider の日次背景
                $summary[$d]['subs_total'] = (int)($e['extendedProps']['count'] ?? 0);
            } elseif (($type ?? '') !== 'BACKGROUND') {
                // ON 系は当日件数カウント
                $summary[$d]['events_total']++;
            }
        }

        // --- Events（編集用）をDBから取得（対象月） ---
        $rows = Event::query()
            ->whereBetween('event_date', [$start->toDateString(), $end->copy()->subDay()->toDateString()])
            ->orderBy('event_date')->orderBy('start_time')
            ->get([
                'id','event_date','original_user_id','Leave_type','sub','title','school_name',
                'start_time','end_time','total_duration','Lesson','assigned_user_id','status','type','notes'
            ]);

        // 画面用データ
        $summaryRows = array_values($summary); // date順
        $eventRows   = $rows->map(function ($r) {
            return [
                'id'                => $r->id,
                'event_date'        => $r->event_date,
                'weekday'           => '', // JSで算出
                'original_user_id'  => $r->original_user_id,
                'Leave_type'        => $r->Leave_type,
                'sub'               => $r->sub,
                'title'             => $r->title,
                'school_name'       => $r->school_name,
                'start_time'        => $r->start_time?->format('H:i'),
                'end_time'          => $r->end_time?->format('H:i'),
                'total_minutes'     => $r->total_minutes,
                'Lesson'            => $r->Lesson,
                'assigned_user_id'  => $r->assigned_user_id,
                'status'            => $r->status,
                'type'              => $r->type,
                'notes'             => $r->notes,
            ];
        })->values()->all();

        return view('calendar.edit', [
            'viewUser'    => $viewUser,
            'month'       => $ym,
            'summaryRows' => $summaryRows,
            'eventRows'   => $eventRows,
        ]);
    }
}
