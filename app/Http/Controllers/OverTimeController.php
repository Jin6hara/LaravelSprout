<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;

class OverTimeController extends Controller
{
    public function index()
    {
        $events = Event::query()
            ->where('type', 'overtime')
            ->where('status', 'in_process')
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->get();

        return view('events.overtime', compact('events'));
    }
}
