<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ScheduleCsv\UserScheduleLineExportService;

class UserScheduleLineExportController extends Controller
{
    public function exportForm()
    {
        return view('csv.user_schedule_line');
    }

    public function download(Request $request, UserScheduleLineExportService $service)
    {
        // 任意のフィルタ
        $filters = [
            'employee_code' => $request->string('employee_code')->toString() ?: null,
            'user_id'       => $request->integer('user_id') ?: null,
            'date_from'     => $request->date('date_from')?->format('Y-m-d'),
            'date_to'       => $request->date('date_to')?->format('Y-m-d'),
        ];

        $filename = 'user_schedule_line_export_' . now()->format('Ymd_His') . '.csv';
        return $service->streamCsv($filters, $filename, addBom: true);
    }
}
