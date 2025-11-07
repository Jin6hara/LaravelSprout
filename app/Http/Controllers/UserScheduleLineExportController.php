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
            'employee_code' => $request->string('employee_code')->toString() ?: null, // 6桁 or 部分一致
            'schedule_id'   => $request->integer('schedule_id') ?: null,
            'date_from'     => $request->date('date_from')?->format('Y-m-d'),
            'date_to'       => $request->date('date_to')?->format('Y-m-d'),
            'active_only'   => $request->boolean('active_only', false),
        ];

        $filename = 'user_schedule_line_export_' . now()->format('Ymd_His') . '.csv';
        return $service->streamCsv($filters, $filename, addBom: true);
    }
}
