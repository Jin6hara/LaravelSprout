<?php

namespace App\Http\Controllers;

use App\Models\ExpenseReport;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ExpenseReportController extends Controller
{
    public function show(Request $request)
    {
        // Asia/Tokyo 前提
        $today = Carbon::now('Asia/Tokyo');

        // year, month をクエリから取得（なければ当月）
        $y = (int)($request->query('year', $today->year));
        $m = (int)($request->query('month', $today->month));

        // ゆるくバリデーション（範囲外は422）
        $this->validate($request, [
            'year'  => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        // レコード取得（社員名はスナップショット列を優先利用）
        $reports = ExpenseReport::query()
            ->select(
                'id',
                'employee_code',
                'employee_family_name',
                'employee_first_middle_name',
                'total_amount',
                'status',
                'submitted_at',
                'year',
                'month'
            )
            ->forYm($y, $m)
            ->orderBy('employee_family_name')
            ->orderBy('employee_first_middle_name')
            ->get();

        // テーブル行（Jspreadsheetのマトリクスにしやすい構造へ）
        $rows = $reports->map(function ($r) {
            return [
                'employee_code' => $r->employee_code,
                'family_name'   => $r->employee_family_name,
                'first_name'    => $r->employee_first_middle_name ?? '',
                'total_amount'  => (int) $r->total_amount, // 円(整数)
                'status'        => $r->status instanceof \BackedEnum ? $r->status->value : (string)$r->status,
                'submitted_at'  => optional($r->submitted_at)->format('Y-m-d H:i'),
            ];
        })->values();

        // ヘッダー用サマリ
        $sum_all       = (int) $reports->sum('total_amount');
        $sum_submitted = (int) $reports->whereNotNull('submitted_at')->sum('total_amount');
        $sum_approved  = (int) $reports->filter(function ($r) {
            $v = $r->status instanceof \BackedEnum ? $r->status->value : (string)$r->status;
            return in_array(strtolower($v), ['approved', 'paid'], true);
        })->sum('total_amount');

        $summary = [
            'count'         => $reports->count(),
            'submitted'     => $reports->whereNotNull('submitted_at')->count(),
            'not_submitted' => $reports->count() - $reports->whereNotNull('submitted_at')->count(),
            // 新しい合計値
            'sum_all'       => $sum_all,
            'sum_submitted' => $sum_submitted,
            'sum_approved'  => $sum_approved, // 任意表示
            'by_status'     => $reports->groupBy(function ($r) {
                return $r->status instanceof \BackedEnum ? $r->status->value : (string)$r->status;
            })->map->count(),
        ];

        return view('expenses.report', [
            'y'      => $y,
            'm'      => $m,
            'rows'   => $rows,
            'summary' => $summary,
        ]);
    }
}
