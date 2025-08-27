<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\{User, Holiday, UserRestPattern, CompanyClosure, RestPatternAdjustment};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        try {

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

            // ===== ユーザーのパターン割当 & 調整（ORD/RWD） ========================
            $assigns = UserRestPattern::with(['pattern.rules'])
                ->where('user_id', $targetUser->id)
                ->activeBetween($start->toDateString(), $end->toDateString())
                ->orderBy('start_date')
                ->get();

            $patternIds = $assigns->pluck('rest_pattern_id')->unique()->values();

            // 対象期間内の調整をパターンID×日付でグループ化
            $adjustments = RestPatternAdjustment::between($start->toDateString(), $end->toDateString())
                ->whereIn('rest_pattern_id', $patternIds)
                ->get()
                ->groupBy([
                    'rest_pattern_id',
                    fn($a) => $a->date->toDateString(),
                ]);

            $offEvents  = collect(); // 法定・所定・ORD
            $rwdEvents  = collect(); // RWD（黄色）
            $offDateSet = [];        // 「この日はOFF扱い」判定用セット（会社休暇の除外に使う）

            // ===== 日ごと生成（祝日はオフ系/RWDとも出さない） =======================
            $cursor = (clone $start)->startOfDay();
            while ($cursor->lte($end)) {
                $ymd = $cursor->toDateString();

                // ★ 祝日ならスキップ
                if ($holidayDates->has($ymd)) {
                    $cursor->addDay();
                    continue;
                }

                // 当日有効な割当
                $assign = $assigns->first(function ($a) use ($cursor) {
                    return $a->start_date->lte($cursor) && (is_null($a->end_date) || $a->end_date->gte($cursor));
                });

                if ($assign && $assign->pattern) {
                    $w    = (int) $cursor->dayOfWeek; // 0(日)〜6(土)
                    $rule = $assign->pattern->rules->firstWhere('weekday', $w); // work / prescribed_off / statutory_off

                    // 当日の調整（その日のパターンに紐づくもののみ）
                    /** @var RestPatternAdjustment|null */ //ワンライナー派
                    $adj = optional(data_get($adjustments, "{$assign->rest_pattern_id}.{$ymd}"))->first();

                    if ($adj && $adj->kind === 'add_off') {
                        // ORD: 所定休を追加（元がworkでもOFF化）※所定休と同じ色
                        $offEvents->push([
                            'title'  => $adj->title ?: '調整休日（ORD）',
                            'start'  => $ymd,
                            'allDay' => true,
                            'classNames' => ['fc-off-prescribed'],
                            'extendedProps' => ['category' => '1_off', 'type' => 'prescribed', 'code' => 'ORD'],
                        ]);
                        $offDateSet[$ymd] = true;
                    } elseif ($adj && $adj->kind === 'work_instead') {
                        // RWD: 元がoffなら取消（勤務扱い）＋黄色で見せる
                        if ($rule && $rule->kind !== 'work') {
                            $rwdEvents->push([
                                'title'  => $adj->title ?: '調整出勤（RWD）',
                                'start'  => $ymd,
                                'allDay' => true,
                                'classNames' => ['fc-rwd'],
                                'extendedProps' => ['category' => '1_off', 'type' => 'rwd', 'code' => 'RWD'],
                            ]);
                            // offDateSet に入れない＝勤務扱い
                        }
                        // もともとworkなら何もしない（視覚だけ出したいなら push も可）

                    } else {
                        // 通常（調整なし）：元がoffなら表示
                        if ($rule && $rule->kind !== 'work') {
                            $isStatutory = $rule->kind === 'statutory_off';
                            $offEvents->push([
                                'title'  => $isStatutory ? 'LRD' : 'ORD',
                                'start'  => $ymd,
                                'allDay' => true,
                                'classNames' => [$isStatutory ? 'fc-off-statutory' : 'fc-off-prescribed'],
                                'extendedProps' => ['category' => '1_off', 'type' => $isStatutory ? 'statutory' : 'prescribed'],
                            ]);
                            $offDateSet[$ymd] = true;
                        }
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

            // ===== マージ順（必要ならここを調整） ===============================
            $events = $offEvents
                ->merge($companyEvents)
                ->merge($rwdEvents)
                ->merge($holidayEvents)
                ->values();

            return response()->json($events);

        } catch (\Throwable $e) {
            Log::error('Calendar events error', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'start' => $request->query('start'),
                'end' => $request->query('end'),
                'user' => $request->query('user_id')
            ]);
            // 画面は空配列で継続（開発中は err レスポンスを console に出すと◎）
            return response()->json([], 200);
        }
    }
}
