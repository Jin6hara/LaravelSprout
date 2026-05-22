<?php

namespace App\Http\Controllers;

use App\Models\ScheduleLine;
use App\Services\ScheduleCsv\ScheduleCsvExportService;
use App\Services\ScheduleCsv\ScheduleCsvImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ScheduleCsvController extends Controller
{
    public function show()
    {
        $this->authorize('viewAny', ScheduleLine::class);

        return view('csv.schedule_csv');
    }

    public function export(ScheduleCsvExportService $service)
    {
        $this->authorize('viewAny', ScheduleLine::class);

        $filename = 'schedules_' . now()->format('Ymd_His') . '.csv';
        return $service->streamCsv($filename);
    }

    public function import(Request $request, ScheduleCsvImportService $service)
    {
        $this->authorize('viewAny', ScheduleLine::class);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = $request->file('csv_file')->getRealPath();

        try {
            $result = $service->import($path);
        } catch (Throwable $e) {
            return back()->with('toast_errors', ['予期しないエラーが発生しました: ' . $e->getMessage()]);
        }

        if (!empty($result['errors'])) {
            return back()->with('toast_errors', $result['errors']);
        }

        $msg = sprintf(
            'インポート完了：schedule_line 作成 %d 件・更新 %d 件、schedule_detail 作成 %d 件・更新 %d 件',
            $result['line_created'],
            $result['line_updated'],
            $result['detail_created'],
            $result['detail_updated'],
        );

        return back()->with('toast', $msg);
    }
}
