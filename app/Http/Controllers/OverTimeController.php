<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\CurrentScopeService;

class OverTimeController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    public function index()
    {
        $districtId = $this->scopeService->currentDistrictId();

        $events = Event::query()
            ->where('type', 'overtime')
            ->where('status', 'in_process')
            ->whereHas('originalUser', fn($q) => $q->where('district_id', $districtId))
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        return view('events.overtime', compact('events'));
    }
}
