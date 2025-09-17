<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseReport;
use Illuminate\Http\Request;

class AdminExpenseController extends Controller
{
    public function index(Request $req)
    {
        $this->authorize('viewAny', \App\Models\ExpenseReport::class); // isAdminのみ

        $y = (int)($req->query('year') ?? now()->year);
        $m = (int)($req->query('month') ?? now()->month);

        $reports = ExpenseReport::query()
            ->where('year', $y)->where('month', $m)
            ->orderBy('employee_code')->paginate(50);

        return view('expenses.admin_index', compact('reports', 'y', 'm'));
    }
}
