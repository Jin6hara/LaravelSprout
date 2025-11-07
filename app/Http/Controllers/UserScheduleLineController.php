<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserScheduleLineCsvImportService;

class UserScheduleLineController extends Controller
{
    public function form()
    {
        return view('csv.user_schedule_line');
    }

    public function import(Request $request, UserScheduleLineCsvImportService $service)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
            'update'   => ['nullable', 'boolean'], // 同一キー行の更新を許可
        ]);

        $doUpdate = $request->boolean('update');
        $filePath = $request->file('csv_file')->getRealPath();

        [$summary, $logs] = $service->import($filePath, $doUpdate);

        return back()->with([
            'toast' => sprintf(
                'Done. created=%d, updated=%d, skipped=%d, missing_user=%d, invalid=%d',
                $summary['created'],
                $summary['updated'],
                $summary['skipped'],
                $summary['missing_user'],
                $summary['invalid']
            ),
            'toast_errors' => $logs,
        ]);
    }
}
