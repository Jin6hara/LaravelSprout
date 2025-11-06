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
            'update'   => ['nullable', 'boolean'], // 既存一致を更新にするか
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $doUpdate = $request->boolean('update');

        [$summary, $errors] = $service->import($path, $doUpdate);

        return back()->with([
            'toast' => sprintf(
                'Done: schedules(created=%d,updated=%d,skipped=%d) | lines=%d | details(upserted=%d,skipped=%d) | missing_user=%d | invalid=%d',
                $summary['sch_created'],
                $summary['sch_updated'],
                $summary['sch_skipped'],
                $summary['line_count'],
                $summary['detail_upserted'],
                $summary['detail_skipped'],
                $summary['missing_user'],
                $summary['invalid']
            ),
            'toast_error' => $errors,
        ]);
    }
}
