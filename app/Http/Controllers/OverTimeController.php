<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\CurrentScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OverTimeController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    public function index()
    {
        if (!Auth::user()->hasRole(['admin', 'super_admin'])) {
            abort(403);
        }

        $targetUserIds = $this->scopeService->targetUserIds();

        $events = Event::query()
            ->where('type', 'overtime')
            ->where('status', 'in_process')
            ->whereIn('assigned_user_id', $targetUserIds)
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        return view('events.overtime', compact('events'));
    }
}
