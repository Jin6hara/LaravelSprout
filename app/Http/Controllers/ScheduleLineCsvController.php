<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ScheduleCsv\ScheduleLineCsvImportService;

class ScheduleLineCsvController extends Controller
{
    public function form()
    {
        return view('csv.schedule_line');
    }

    public function import(Request $request, ScheduleLineCsvImportService $service)
    {
        $request->validate([
            'csv_file' => ['required','file','mimes:csv,txt'],
            'schedule_id' => ['nullable','integer']
        ]);

        $filePath = $request->file('csv_file')->getRealPath();
        $scheduleId = $request->input('schedule_id');

        $result = $service->import($filePath, $scheduleId);

        return back()->with('toast', "インポート完了: {$result['count']} 行");
    }
}
