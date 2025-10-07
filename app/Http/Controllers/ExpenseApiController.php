<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseReport;
use App\Models\CommuterPass;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use App\Enums\ExpenseReportStatus;

class ExpenseApiController extends Controller
{
    // 追加
    public function store(Request $req)
    {
        $data = $req->validate([
            'expense_report_id' => ['required', 'exists:expense_reports,id'],
            'expense_date'      => ['required', 'date'],
            'seq'               => ['required', 'integer', 'min:0'],
            'station_from'      => ['nullable', 'string', 'max:255'],
            'station_to'        => ['nullable', 'string', 'max:255'],
            'note'              => ['nullable', 'string'],
            'cost'              => ['nullable', 'integer', 'min:0'],
            'trip_type'         => ['required', Rule::in(['round_trip', 'one_way'])],
            'category'          => ['required', Rule::in(['regular', 'irregular'])],
            'commuter_pass_id'  => ['nullable', 'exists:commuter_passes,id'],
        ]);

        $report = ExpenseReport::findOrFail($data['expense_report_id']);

        // 権限（本人 or 管理者）: 必要に応じて置き換え
        if (method_exists($req->user(), 'isAdmin') && !$req->user()->isAdmin()) {
            abort_unless($req->user()->id === $report->user_id, 403);
        }

        // 月内チェック
        $d = Carbon::parse($data['expense_date']);
        abort_unless($d->year == $report->year && $d->month == $report->month, 422, 'Date out of report month.');

        // パスの所有者整合
        if (!empty($data['commuter_pass_id'])) {
            $ok = CommuterPass::where('id', $data['commuter_pass_id'])->where('user_id', $report->user_id)->exists();
            abort_unless($ok, 422, 'Invalid commuter pass owner.');
        }

        $exp = Expense::create([
            'expense_report_id' => $report->id,
            'expense_date'      => $data['expense_date'],
            'seq'               => $data['seq'],
            'station_from'      => $data['station_from'] ?? null,
            'station_to'        => $data['station_to'] ?? null,
            'note'              => $data['note'] ?? null,
            'cost'              => $data['cost'] ?? 0,
            'trip_type'         => $data['trip_type'],
            'category'          => $data['category'],
            'commuter_pass_id'  => $data['commuter_pass_id'] ?? null,
        ]);

        return response()->json($exp->fresh(), 201);
    }

    // 更新（保存ボタン用／1行ずつ）
    public function update(Request $req, Expense $expense)
    {
        $report = $expense->report;

        if (method_exists($req->user(), 'isAdmin') && !$req->user()->isAdmin()) {
            abort_unless($req->user()->id === $report->user_id, 403);
        }

        $data = $req->validate([
            'station_from'      => ['nullable', 'string', 'max:255'],
            'station_to'        => ['nullable', 'string', 'max:255'],
            'note'              => ['nullable', 'string'],
            'cost'              => ['nullable', 'integer', 'min:0'],
            'trip_type'         => ['nullable', Rule::in(['round_trip', 'one_way'])],
            'category'          => ['nullable', Rule::in(['regular', 'irregular'])],
            'commuter_pass_id'  => ['nullable', 'exists:commuter_passes,id'],
            'seq'               => ['nullable', 'integer', 'min:0'],
        ]);

        if (!empty($data['commuter_pass_id'])) {
            $ok = CommuterPass::where('id', $data['commuter_pass_id'])->where('user_id', $report->user_id)->exists();
            abort_unless($ok, 422, 'Invalid commuter pass owner.');
        }

        $expense->fill($data)->save();

        return response()->json($expense->fresh());
    }

    // 削除
    public function destroy(Request $req, Expense $expense)
    {
        $report = $expense->report;

        if (method_exists($req->user(), 'isAdmin') && !$req->user()->isAdmin()) {
            abort_unless($req->user()->id === $report->user_id, 403);
        }

        $expense->delete();
        return response()->json(['ok' => true]);
    }

        public function submitReport(ExpenseReport $report, Request $request)
    {
        // 認可：本人 or 管理者（運用に合わせて）
        if (method_exists($request->user(), 'isAdmin')) {
            $authorized = $request->user()->isAdmin() || $request->user()->id === $report->user_id;
        } else {
            $authorized = $request->user()->id === $report->user_id;
        }
        abort_unless($authorized, 403);

        // すでに提出済みならそのまま返す（冪等）
        if ($report->status === ExpenseReportStatus::SUBMITTED->value) {
            return response()->json([
                'id'           => $report->id,
                'status'       => $report->status,
                'submitted_at' => optional($report->submitted_at)->toISOString(),
            ]);
        }

        // 更新（タイムゾーンはJST）
        $report->status       = ExpenseReportStatus::SUBMITTED->value;
        $report->submitted_at = now('Asia/Tokyo');
        $report->save();

        return response()->json([
            'id'           => $report->id,
            'status'       => $report->status,
            'submitted_at' => optional($report->submitted_at)->toISOString(),
        ]);
    }
}
