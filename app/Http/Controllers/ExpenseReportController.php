<?php

namespace App\Http\Controllers;

use App\Models\ExpenseReport;
use App\Services\ExpenseEligibilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExpenseReportController extends Controller
{
    public function show(ExpenseReport $report)
    {
        $this->authorize('view', $report);

        return view('expenses.edit', [
            'report'   => $report,
            'expenses' => $report->expenses()->orderBy('expense_date')->get(),
        ]);
    }

    public function flags(ExpenseReport $report, ExpenseEligibilityService $svc)
    {
        $this->authorize('view', $report);
        $month = Carbon::create($report->year, $report->month, 1);
        return response()->json($svc->buildMonthlyFlags($report->user, $month));
    }

    public function submit(ExpenseReport $report)
    {
        $this->authorize('update', $report);
        $report->update(['submitted_at' => now(), 'status' => 'submitted']);
        return back()->with('ok', 'Submitted');
    }
}
