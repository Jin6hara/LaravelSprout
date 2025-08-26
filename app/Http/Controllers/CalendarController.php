<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Carbon\CarbonPeriod;
use App\Models\{User, Holiday, UserRestPattern};
use Illuminate\Support\Facades\Auth;

class CalendarController extends Controller
{
    public function index(Request $request, ?User $user = null)
    {
        $viewer = Auth::user();
        $viewUser = $user ?? $viewer; // 自分
        // general は自分のみ、admin/super_admin は誰でも
        if (!$viewer->hasRole(['admin', 'super_admin']) && $viewUser->id !== $viewer->id) {
            abort(403);
        }
        return view('calendar.index', ['viewUser' => $viewUser]);
    }

    // FullCalendarが ?start=YYYY-MM-DD&end=YYYY-MM-DD で叩く想定
    public function events(Request $request)
    {
        $viewer = auth()->user();
        $start = Carbon::parse($request->query('start', now()->startOfYear()))->startOfDay();
        $end   = Carbon::parse($request->query('end',   now()->endOfYear()))->endOfDay();

        $targetUserId = (int) $request->query('user_id', $viewer->id);
        if (!$viewer->hasRole(['admin', 'super_admin']) && $targetUserId !== $viewer->id) abort(403);
        $targetUser = User::findOrFail($targetUserId);

        // 祝日（背景表示）
        $holidays = Holiday::between($start->toDateString(), $end->toDateString())
            ->get()
            ->map(function ($h) {
                $e = $h->toCalendarEvent();
                $e['display'] = 'background';
                $e['classNames'] = array_unique(array_merge($e['classNames'] ?? [], ['fc-holiday']));
                return $e;
            });

        // ★ 祝日の「日付集合」を作る（同日の所定/法定休は出さない）
        $holidayDates = collect($holidays)
            ->flatMap(function ($e) {
                $s = Carbon::parse($e['start'])->toDateString();
                $t = isset($e['end']) ? Carbon::parse($e['end'])->subDay()->toDateString() : $s; // endが翌日境界なら-1日
                if ($s === $t) return [$s];
                $period = CarbonPeriod::create($s, $t);
                return collect($period)->map->toDateString()->all();
            })
            ->unique()
            ->flip(); // has() でO(1)判定するため

        // ユーザーの休日パターンから「所定/法定休」を生成
        $assigns = UserRestPattern::with(['pattern.rules'])
            ->where('user_id', $targetUser->id)
            ->activeBetween($start->toDateString(), $end->toDateString())
            ->orderBy('start_date')
            ->get();

        $offEvents = collect();
        $cursor = (clone $start);
        while ($cursor <= $end) {
            $ymd = $cursor->toDateString();

            // ★ 祝日ならスキップ
            if ($holidayDates->has($ymd)) {
                $cursor->addDay();
                continue;
            }

            $assign = $assigns->first(function ($a) use ($cursor) {
                return $a->start_date->lte($cursor) && (is_null($a->end_date) || $a->end_date->gte($cursor));
            });

            if ($assign && $assign->pattern) {
                $w = (int) $cursor->dayOfWeek; // 0(日)〜6(土)
                $rule = $assign->pattern->rules->firstWhere('weekday', $w);
                if ($rule && $rule->kind !== 'work') {
                    $isStatutory = $rule->kind === 'statutory_off';
                    $offEvents->push([
                        'title'  => $isStatutory ? '法定休' : '所定休',
                        'start'  => $ymd,
                        'allDay' => true,
                        'classNames' => ['fc-regular'], // [$isStatutory ? 'fc-off-statutory' : 'fc-off-prescribed']
                        'extendedProps' => ['category' => '1_off', 'type' => $isStatutory ? 'statutory' : 'prescribed'],
                    ]);
                }
            }
            $cursor->addDay();
        }

        return response()->json($offEvents->merge($holidays)->values());
    }
}
