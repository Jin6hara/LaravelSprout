<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\ExpenseReport;

class ExpenseReportController extends Controller
{
    public function show(Request $request)
    {
        // 年月の解決（未指定なら現在〈Asia/Tokyo〉）
        $nowJst = Carbon::now('Asia/Tokyo');
        $year  = (int) $request->input('year', $nowJst->year);
        $month = (int) $request->input('month', $nowJst->month);

        // 対象月のレポートを取得（スナップショット列を優先）
        $reports = ExpenseReport::query()
            ->where('year', $year)
            ->where('month', $month)
            ->orderBy('employee_code')
            ->get([
                'employee_code',
                'employee_family_name',
                'employee_first_middle_name',
                'total_amount',
                'status',
                'submitted_at',
            ]);

        // JSpreadsheet 行データに整形（表示名を要件に合わせてキー変換）
        $rows = $reports->map(function ($r) {
            return [
                'employee_code' => $r->employee_code,
                'family_name'   => $r->employee_family_name,
                'first_name'    => $r->employee_first_middle_name,
                'total_amount'  => (int) $r->total_amount,
                'status'        => is_object($r->status) ? $r->status->value : (string)$r->status, // ← 修正！
                'submitted_at'  => optional($r->submitted_at)->format('Y-m-d H:i'),
            ];
        })->values();

        // 合計などのヘッダ表示用
        $summary = [
            'count'  => $reports->count(),
            'total'  => $reports->sum('total_amount'),
            'year'   => $year,
            'month'  => $month,
        ];

        return view('expenses.report', [
            'rows'    => $rows,
            'summary' => $summary,
        ]);
    }
}
