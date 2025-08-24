<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use App\Models\Shift;//
use App\Models\Holiday;//
use App\Models\CoverageNeed;//
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    public function index()
    {
        return view('calendar.index'); // BladeでFullCalendarを初期化
    }

    // FullCalendarが ?start=YYYY-MM-DD&end=YYYY-MM-DD を付けてGETします
    public function events(Request $request)
    {
        $start = $request->query('start'); // 文字列（ISO）
        $end   = $request->query('end');

        $user = Auth::user();
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super_admin');

        $events = [];

        // 下記は燃料の入り口（サンプル）

        // 祝日（全員に表示）
        $holidays = Holiday::whereBetween('date', [$start, $end])->get();
        foreach ($holidays as $h) {
            $events[] = [
                'id' => 'holiday-' . $h->id,
                'title' => '祝日：' . $h->name,
                'start' => $h->date->toDateString(),
                'allDay' => true,
                'display' => 'background', // 背景マーキング
                'classNames' => ['fc-holiday'],
            ];
        }

        if ($isAdmin) {
            // 管理者：サブ募集（CoverageNeed）をイベントとして表示（件数をタイトルに）
            $needs = CoverageNeed::whereBetween('date', [$start, $end])->get()
                ->groupBy('date');

            foreach ($needs as $date => $items) {
                $count = $items->sum('needed');
                $events[] = [
                    'id' => 'need-' . $date,
                    'title' => "サブ必要 {$count}件",
                    'start' => $date,
                    'allDay' => true,
                    'classNames' => ['fc-need'],
                    'extendedProps' => [
                        'details' => $items->map(fn($it) => [
                            'campus' => $it->campus,
                            'needed' => $it->needed,
                            'reason' => $it->reason
                        ])->values(),
                    ],
                ];
            }
        } else {
            // 講師：自分のシフト
            $shifts = Shift::where('user_id', $user->id)
                ->whereBetween('date', [$start, $end])
                ->orderBy('date')
                ->get();

            foreach ($shifts as $s) {
                $label = $s->type === 'overtime' ? '残業' : '通常';
                $time  = ($s->start_time && $s->end_time) ? " {$s->start_time}–{$s->end_time}" : '';
                $events[] = [
                    'id' => 'shift-' . $s->id,
                    'title' => "{$label}{$time} @{$s->location}",
                    'start' => $s->date->toDateString(),
                    'allDay' => true, // 日単位管理の想定。時間帯で出すなら allDay=false に
                    'classNames' => [$s->type === 'overtime' ? 'fc-overtime' : 'fc-regular'],
                    'extendedProps' => [
                        'type' => $s->type,
                        'location' => $s->location,
                        'start_time' => $s->start_time,
                        'end_time' => $s->end_time,
                    ],
                ];
            }
        }

        return response()->json($events);
    }
}
