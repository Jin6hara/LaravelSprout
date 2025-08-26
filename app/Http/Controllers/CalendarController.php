<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Carbon\CarbonPeriod;
use App\Models\{User, Holiday, UserRestPattern, CompanyClosure};
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

        $targetUserId = (int) $request->query('user_id', $viewer->id);
        if (!$viewer->hasRole(['admin', 'super_admin']) && $targetUserId !== $viewer->id) abort(403);
        $targetUser = User::findOrFail($targetUserId);

        // ========== 祝日（背景） ==========
        $holidayEvents = Holiday::between($start->toDateString(), $end->toDateString())
            ->get()
            ->map(function ($h) {
                $e = (array)$h->toCalendarEvent();   // 念のため配列化
                $e['display'] = 'background';
                $classes = $e['classNames'] ?? [];
                $e['classNames'] = array_unique(array_merge(is_array($classes) ? $classes : [$classes], ['fc-holiday']));
                return $e;
            });

        // 祝日の日付セット（高速判定用）
        $holidayDates = $holidayEvents
            ->map(fn($e) => Carbon::parse($e['start'])->toDateString())
            ->unique()->flip();

        // ========== ユーザーの所定/法定休（前回のロジック） ==========
        $assigns = UserRestPattern::with(['pattern.rules'])
            ->where('user_id', $targetUser->id)
            ->activeBetween($start->toDateString(), $end->toDateString())
            ->orderBy('start_date')
            ->get();

        $offEvents = collect();
        $offDateSet = []; // 所定/法定休の日付セット

        $cursor = (clone $start)->startOfDay();
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
                        'title'  => $isStatutory ? 'LRD' : 'ORD',
                        'start'  => $ymd,
                        'allDay' => true,
                        'classNames' => [$isStatutory ? 'fc-off-statutory' : 'fc-off-prescribed'], //CSSで色分けする
                        'extendedProps' => ['category' => '1_off', 'type' => $isStatutory ? 'statutory' : 'prescribed'],
                    ]);
                    $offDateSet[$ymd] = true;
                }
            }
            $cursor->addDay();
        }

        // ========== 会社の長期休暇（緑） ==========
        $companyEvents = collect();
        $closures = CompanyClosure::between($start->toDateString(), $end->toDateString())->get();

        foreach ($closures as $c) {
            $period = \Carbon\CarbonPeriod::create($c->start_date, $c->end_date); // 包含
            $shownCount = 0; // ← この休暇レコードで「表示した日数」をカウント

            foreach ($period as $d) {
                $ymd = $d->toDateString();

                // 祝日 or 所定/法定休 に該当する日はスキップ（表示対象外）
                if (isset($holidayDates[$ymd]) || isset($offDateSet[$ymd])) {
                    continue;
                }

                // SB / WB の「最初の5つ」だけ Fixed ALP にする
                $title = $c->name; // デフォルトは元の名称（ゴールデンウイーク/夏休み/冬休み）
                if (in_array($c->code, ['SB', 'WB'], true)) {
                    if ($shownCount < 5) {
                        $title = 'Fixed ALP';
                    }
                    $shownCount++; // フィルタを通った「表示実績」をカウント
                }

                $companyEvents->push([
                    'title'  => $title,
                    'start'  => $ymd,
                    'allDay' => true,
                    'classNames' => ['fc-company-break'],
                    'extendedProps' => [
                        'category' => '0_company',
                        'type' => 'company_break',
                        'code' => $c->code,
                        'original_name' => $c->name, // 追跡したければ
                        'is_fixed_alp' => ($title === 'Fixed ALP'),
                        'index_in_closure' => $shownCount, // 任意
                    ],
                ]);
            }
        }

        // 祝日（背景）→ 会社休暇（緑）→ 所定/法定（グレー）
        $events = $offEvents->merge($companyEvents)->merge($holidayEvents)->values();

        return response()->json($events);
    }
}
