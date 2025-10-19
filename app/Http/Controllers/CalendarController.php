<?php

namespace App\Http\Controllers;

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\Calendar\CalendarEventService;

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

        // ★ 追加：管理者だけにセレクト用リストを渡す（一般は空のコレクション）
        $userOptions = $viewer->hasRole(['admin', 'super_admin'])
            ? User::query()
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

    public function events(Request $request, \App\Services\Calendar\CalendarResolver $resolver)
    {
        try {
            $viewer = Auth::user();
            $start = Carbon::parse($request->query('start', now()->startOfYear()))->startOfDay();
            $end   = Carbon::parse($request->query('end',   now()->endOfYear()))->endOfDay();

            $targetUserId = (int) $request->query('user_id', $viewer->id);
            if (!$viewer->hasRole(['admin', 'super_admin']) && $targetUserId !== $viewer->id) abort(403);
            $user = User::findOrFail($targetUserId);

            $events = $resolver->build($user, $start, $end);
            return response()->json($events);
        } catch (\Throwable $e) {
            Log::error('Calendar events error', ['msg' => $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile()]);
            return response()->json([], 200);
        }
    }

    public function forecast()
    {
        // index.blade.php と同じレイアウト・構成を流用
        // 役割バッジなどもそのままでOK
        $viewUser = auth()->user();
        return view('calendar.forecast', compact('viewUser'));
    }

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
}
