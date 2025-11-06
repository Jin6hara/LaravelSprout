<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserScheduleCsvImportService;

class UserScheduleCsvController extends Controller
{
    public function form()
    {
        return view('csv.user_schedule');
    }

    public function import(Request $request, UserScheduleCsvImportService $service)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
            'update'   => ['nullable', 'boolean'], // 既存の一致行を更新するか
        ]);

        $filePath = $request->file('csv_file')->getRealPath();
        $doUpdate = (bool) $request->boolean('update');

        [$summary, $errors] = $service->import($filePath, $doUpdate);

        return back()->with([
            'toast' => sprintf('完了: created=%d, updated=%d, skipped=%d, missing_user=%d, invalid=%d',
                        $summary['created'], $summary['updated'], $summary['skipped'],
                        $summary['missing_user'], $summary['invalid']),
            'errors' => $errors, // 簡易エラーログ（配列）
        ]);
    }
}
