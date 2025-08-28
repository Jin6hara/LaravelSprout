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
        return view('calendar.index', ['viewUser' => $viewUser]);
    }

    public function events(Request $request, CalendarEventService $svc)
    {
        try {
            $viewer = Auth::user();

            $start = Carbon::parse($request->query('start', now()->startOfYear()))->startOfDay();
            $end   = Carbon::parse($request->query('end',   now()->endOfYear()))->endOfDay();

            $targetUserId = (int) $request->query('user_id', $viewer->id);
            if (!$viewer->hasRole(['admin', 'super_admin']) && $targetUserId !== $viewer->id) abort(403);
            $targetUser = User::findOrFail($targetUserId);

            $events = $svc->build($targetUser, $start, $end);
            return response()->json($events);
        } catch (\Throwable $e) {
            Log::error('Calendar events error', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'start' => $request->query('start'),
                'end' => $request->query('end'),
                'user' => $request->query('user_id'),
            ]);
            return response()->json([], 200);
        }
    }
}
