<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\Calendar\CalendarEventService;
use App\Services\CurrentScopeService;

class CalendarController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    /**
     * カレンダートップ画面
     * GET /calendar — 管理者はユーザー選択リスト付き、一般ユーザーは自分のカレンダーを表示
     */
    public function index(Request $request, ?User $user = null)
    {
        $viewer = Auth::user();
        $canViewAnyUser = $viewer->can('viewAny', User::class);
        $viewUser = $user ?? ($canViewAnyUser ? null : $viewer);
        $this->authorize('view', $user ?? $viewer);

        // スコープが切り替わった後に古いユーザーのURLが残っている場合はリセット
        if ($canViewAnyUser && $viewUser !== null) {
            $currentDid = $this->scopeService->currentDistrictId();
            if ($currentDid !== null && $viewUser->district_id !== $currentDid) {
                return redirect()->route('calendar.index');
            }
        }

        // 管理者だけにセレクト用リストを渡す（district/department で直接絞り込み）
        $userOptions = $canViewAnyUser
            ? User::query()
                ->when($this->scopeService->currentDistrictId(),
                    fn($q, $did) => $q->where('district_id', $did))
                ->when($this->scopeService->currentDepartmentId(),
                    fn($q, $dep) => $q->where('department_id', $dep))
                ->select('id', 'first_name', 'family_name', 'employee_code')
                ->orderBy('employee_code')
                ->limit(500)
                ->get()
            : collect();

        return view('calendar.index', [
            'viewUser'    => $viewUser,
            'userOptions' => $userOptions, // ← 常に渡す（一般は空）
        ]);
    }

    /**
     * カレンダーイベントを JSON で返す（FullCalendar 用）
     * GET /calendar/events — user_id 未指定かつ管理者の場合は祝日・会社休業日のみ返す
     */
    public function events(Request $request, \App\Services\Calendar\CalendarResolver $resolver)
    {
        try {
            $viewer = Auth::user();
            $start = Carbon::parse($request->query('start', now()->startOfYear()))->startOfDay();
            $end   = Carbon::parse($request->query('end',   now()->endOfYear()))->endOfDay();

            $rawUserId = $request->query('user_id');
            $noUserSelected = $rawUserId === null || $rawUserId === '' || $rawUserId === 'null' || (int)$rawUserId <= 0;

            if ($noUserSelected && $viewer->can('viewAny', User::class)) {
                $holidays = app(\App\Services\Calendar\Providers\HolidayProvider::class)->provide($viewer, $start, $end);
                $closures = app(\App\Services\Calendar\Providers\ClosureProvider::class)->provide($viewer, $start, $end);
                $holidayDates = array_flip(array_map(fn($e) => $e->dateKey, $holidays));
                $filteredClosures = array_filter($closures, fn($e) => !isset($holidayDates[$e->dateKey]));
                $events = array_map(fn($e) => $e->toArray(), array_merge($holidays, array_values($filteredClosures)));
                return response()->json($events);
            }

            $targetUserId = (int) $request->query('user_id', $viewer->id);
            $user = User::findOrFail($targetUserId);
            $this->authorize('view', $user);

            $events = $resolver->build($user, $start, $end);
            return response()->json($events);
        } catch (\Throwable $e) {
            Log::error('Calendar events error', ['msg' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
            return response()->json([], 200);
        }
    }

    /**
     * 先行予定カレンダー画面
     * GET /calendar/forecast — 自分の将来のシフト・休暇予定を一覧表示する
     */
    public function forecast()
    {
        // index.blade.php と同じレイアウト・構成を流用
        // 役割バッジなどもそのままでOK
        $viewUser = auth()->user();
        return view('calendar.forecast', compact('viewUser'));
    }

    /**
     * 先行予定カレンダーのイベントを JSON で返す（FullCalendar 用）
     * GET /calendar/forecast/events — ForecastResolver でシフト・休暇の将来予定を取得して返す
     */
    public function forecastEvents(
        \Illuminate\Http\Request $request,
        \App\Services\Calendar\ForecastResolver $resolver
    ) {
        $user  = auth()->user(); // 祝日/会社休みはユーザーに依存しないが、将来の拡張のため合わせておく
        $start = \Carbon\Carbon::parse($request->query('start'));
        $end   = \Carbon\Carbon::parse($request->query('end'));

        $events = $resolver->build($user, $start, $end);
        return response()->json($events);
    }

    /**
     * 休暇カレンダー画面
     * GET /calendar/leave — 自分の休暇取得状況をカレンダー形式で表示する
     */
    public function leave()
    {
        // index.blade.php と同じレイアウト・構成を流用
        // 役割バッジなどもそのままでOK
        $viewUser = auth()->user();
        return view('calendar.leave', compact('viewUser'));
    }

    /**
     * 休暇カレンダーのイベントを JSON で返す（FullCalendar 用）
     * GET /calendar/leave/events — LeaveResolver で休暇イベントを取得して返す
     */
    public function leaveEvents(
        \Illuminate\Http\Request $request,
        \App\Services\Calendar\LeaveResolver $resolver
    ) {
        $user  = auth()->user(); // 祝日/会社休みはユーザーに依存しないが、将来の拡張のため合わせておく
        $start = \Carbon\Carbon::parse($request->query('start'));
        $end   = \Carbon\Carbon::parse($request->query('end'));

        $events = $resolver->build($user, $start, $end);
        return response()->json($events);
    }
}
