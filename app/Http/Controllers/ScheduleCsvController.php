<?php

namespace App\Http\Controllers;

use App\Services\ScheduleCsv\ScheduleCsvExportService;
use App\Services\ScheduleCsv\ScheduleCsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ScheduleCsvController extends Controller
{
    public function show()
    {
        return view('csv.schedule_csv');
    }

    public function export(ScheduleCsvExportService $service)
    {
        $filename = 'schedules_' . now()->format('Ymd_His') . '.csv';
        return $service->streamCsv($filename);
    }

    public function import(Request $request, ScheduleCsvImportService $service)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = $request->file('csv_file')->getRealPath();

        try {
            $result = $service->import($path);
        } catch (Throwable $e) {
            return back()->with('import_errors', ['予期しないエラーが発生しました: ' . $e->getMessage()]);
        }

        if (!empty($result['errors'])) {
            return back()->with('import_errors', $result['errors']);
        }

        $msg = sprintf(
            'インポート完了：schedule_line 作成 %d 件・更新 %d 件、schedule_detail 作成 %d 件・更新 %d 件',
            $result['line_created'],
            $result['line_updated'],
            $result['detail_created'],
            $result['detail_updated'],
        );

        return back()->with('import_success', $msg);
    }
}
