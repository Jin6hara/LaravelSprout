<?php

namespace App\Http\Controllers\Admin\Export;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminExpenseExportController extends Controller
{
    public function csv(Request $req): StreamedResponse
    {
        $this->authorize('viewAny', \App\Models\ExpenseReport::class); // isAdminのみ
        $y = (int)($req->query('year') ?? now()->year);
        $m = (int)($req->query('month') ?? now()->month);

        $filename = "expenses_{$y}_{$m}.csv";
        $headers = ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => "attachment; filename={$filename}"];

        return response()->stream(function () use ($y, $m) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['employee_code', 'name', 'date', 'station_from', 'station_to', 'cost', 'note', 'trip_type', 'category']);

            Expense::query()
                ->whereHas('report', fn($q) => $q->where('year', $y)->where('month', $m))
                ->with('report')
                ->orderBy('user_id')->orderBy('expense_date')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $e) {
                        $name = $e->report->employee_family_name . ' ' . $e->report->employee_first_middle_name;
                        fputcsv($out, [
                            $e->report->employee_code,
                            $name,
                            $e->expense_date->toDateString(),
                            $e->station_from,
                            $e->station_to,
                            $e->cost,
                            $e->note,
                            $e->trip_type->value ?? $e->trip_type,
                            $e->category->value  ?? $e->category,
                        ]);
                    }
                });

            fclose($out);
        }, 200, $headers);
    }
}
