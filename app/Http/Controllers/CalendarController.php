<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use App\Models\Holiday; //
use App\Models\CompanyClosure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function index()
    {
        // resources/views/calendar/index.blade.php を表示（既に存在とのこと）
        return view('calendar.index');
    }

    // FullCalendarが ?start=YYYY-MM-DD&end=YYYY-MM-DD で叩く想定
    public function events(Request $request)
    {
        $start = Carbon::parse($request->query('start', now()->startOfYear()))->toDateString();
        $end   = Carbon::parse($request->query('end', now()->endOfYear()))->toDateString();

        $holidays = Holiday::between($start, $end)->get()->map->toCalendarEvent();
        $closures = CompanyClosure::between($start, $end)->get()->map->toCalendarEvent();

        // 同日重複（例：祝日かつ会社特休）なら company_off を優先して色を上書きしたい場合は、ここでマージロジック調整
        $events = collect($holidays)->merge($closures)->values();

        return response()->json($events);
    }
}
