<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $viewer = Auth::user();
        $start = Carbon::parse($request->query('start', now()->startOfYear()))->startOfDay();
        $end   = Carbon::parse($request->query('end',   now()->endOfYear()))->endOfDay();

        // 対象ユーザーの確定
        $targetUserId = (int) $request->query('user_id', $viewer->id);
        if (!$viewer->hasRole(['admin', 'super_admin']) && $targetUserId !== $viewer->id) {
            abort(403);
        }
        $targetUser = User::findOrFail($targetUserId);

        // 祝日（背景）
        $holidays = Holiday::between($start->toDateString(), $end->toDateString())
            ->get()
            ->map(function ($h) {
                $e = $h->toCalendarEvent(); // 既存のメソッド想定
                // 背景表示を明示（CSS .fc-holiday を当てる）
                $e['display'] = 'background';
                $e['classNames'] = array_unique(array_merge($e['classNames'] ?? [], ['fc-holiday']));
                return $e;
            });

        // ユーザーの有効パターンを期間から決定（複数期間に跨る可能性）
        $assigns = UserRestPattern::with(['pattern.rules'])
            ->where('user_id', $targetUser->id)
            ->activeBetween($start->toDateString(), $end->toDateString())
            ->orderBy('start_date')
            ->get();

        // 曜日 → kind マップを期間別に適用してイベント生成
        $offEvents = collect();
        $cursor = (clone $start)->startOfDay();

        while ($cursor <= $end) {
            // この日の適用アサイン（開始が最も新しいものを優先）
            $assign = $assigns->first(function ($a) use ($cursor) {
                $okStart = $a->start_date->lte($cursor);
                $okEnd   = is_null($a->end_date) || $a->end_date->gte($cursor);
                return $okStart && $okEnd;
            });
            if ($assign && $assign->pattern) {
                $w = (int) $cursor->dayOfWeek; // 0(日)〜6(土)
                $rule = $assign->pattern->rules->firstWhere('weekday', $w);
                if ($rule && $rule->kind !== 'work') {
                    $title = $rule->kind === 'statutory_off' ? '法定休' : '所定休';
                    $offEvents->push([
                        'title'      => $title,
                        'start'      => $cursor->toDateString(),
                        'allDay'     => true,
                        'classNames' => ['fc-regular'],
                        'extendedProps' => [
                            'type' => $title,
                            'category' => '1_off', // 並び順制御したい場合用
                        ],
                    ]);
                }
            }
            $cursor->addDay();
        }

        // マージ（祝日は背景 / 休日は通常イベント）=> 祝日が必ず視認できる
        $events = $offEvents->merge($holidays)->values();
        return response()->json($events);
    }
}
