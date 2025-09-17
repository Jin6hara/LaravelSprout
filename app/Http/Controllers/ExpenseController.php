<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Models\ExpenseReport;

class ExpenseController extends Controller
{
    public function store(StoreExpenseRequest $req)
    {
        $report = ExpenseReport::findOrFail($req->input('expense_report_id'));
        $this->authorize('update', $report);

        $exp = Expense::create($req->validated() + ['user_id' => $report->user_id]);
        return response()->json($exp, 201);
    }

    public function update(UpdateExpenseRequest $req, Expense $expense)
    {
        $this->authorize('update', $expense->report);
        $expense->update($req->validated());
        return response()->json($expense);
    }

    public function destroy(Expense $expense)
    {
        $this->authorize('update', $expense->report);
        $expense->delete();
        return response()->noContent();
    }
}
