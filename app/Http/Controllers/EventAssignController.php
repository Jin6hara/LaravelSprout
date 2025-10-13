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

        // ← ここを月から日へ
        $dateStr = $request->query('date', now()->toDateString());
        $date    = \Carbon\Carbon::parse($dateStr)->startOfDay();

        $start = $date->copy();              // [date 00:00, date+1 00:00)
        $end   = $date->copy()->addDay();

        // ForecastResolver で該当日のイベント群を取得
        $evs = $resolver->build($viewUser, $start, $end);

        // --- Summary 1行だけ ---
        $weekday = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$date->dayOfWeek];
        $subsTotal = 0;
        $eventsTotal = 0;
        foreach ($evs as $e) {
            $type = $e['type'] ?? 'ON';
            if (($e['extendedProps']['category'] ?? null) === 'subcount') {
                $subsTotal = (int)($e['extendedProps']['count'] ?? 0);
            } elseif ($type !== 'BACKGROUND') {
                $eventsTotal++;
            }
        }
        $summaryRows = [[
            'date'        => $date->toDateString(),
            'weekday'     => $weekday,
            'subs_total'  => $subsTotal,
            'events_total' => $eventsTotal,
        ]];

        // --- Events：その日のみ ---
        $rows = Event::query()
            ->whereDate('event_date', $date->toDateString())
            ->orderBy('start_time')
            ->get([
                'id',
                'event_date',
                'original_user_id',
                'Leave_type',
                'sub',
                'title',
                'school_name',
                'start_time',
                'end_time',
                'total_duration',
                'Lesson',
                'assigned_user_id',
                'status',
                'type',
                'notes'
            ]);

        $eventRows = $rows->map(function ($r) {
            return [
                'id'                => $r->id,
                'event_date'        => $r->event_date,                   // Y-m-d
                'weekday'           => '',                                // JSで算出
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
            'date'        => $date->toDateString(),  // ← 追加：当日
            'summaryRows' => $summaryRows,
            'eventRows'   => $eventRows,
        ]);
    }
}
