<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseApi\BatchSaveExpenseRequest;
use App\Http\Requests\ExpenseApi\StoreExpenseRequest;
use App\Http\Requests\ExpenseApi\UpdateExpenseRequest;
use App\Models\CommuterPass;
use App\Models\Expense;
use App\Models\ExpenseReport;
use App\Enums\ExpenseReportStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExpenseApiController extends Controller
{
    // 共有: ロック検査
    private function abortIfLocked(ExpenseReport $report)
    {
        // 提出以降はロック（必要に応じてapproved/paidも）
        $lockedStatuses = [
            ExpenseReportStatus::SUBMITTED->value,
            ExpenseReportStatus::APPROVED->value,
            ExpenseReportStatus::PAID->value,
        ];

        // ✅ Enumにも対応（キャストされていない場合の対策）
        $statusValue = $report->status instanceof ExpenseReportStatus
            ? $report->status->value
            : (string) $report->status;

        if (in_array($statusValue, $lockedStatuses, true)) {
            abort(423, 'This expense report is locked (submitted).');
        }
    }

    /**
     * 経費明細を一括保存（更新＋新規作成）
     * PUT /api/expenses/batch — 1リクエストでトランザクション処理
     */
    public function batchSave(BatchSaveExpenseRequest $req)
    {
        $data   = $req->validated();
        $report = ExpenseReport::findOrFail($data['report_id']);
        $this->authorize('update', $report);
        $this->abortIfLocked($report);

        // updates の全 ID が このレポートに属することを確認
        $updateIds = collect($data['updates'])->pluck('id');
        if ($updateIds->isNotEmpty()) {
            $belongCount = Expense::whereIn('id', $updateIds)
                ->where('expense_report_id', $report->id)
                ->count();
            abort_if($belongCount !== $updateIds->count(), 403, 'Some expense IDs do not belong to this report.');
        }

        // creates の日付が全てこのレポートの月内であることを確認
        foreach ($data['creates'] as $c) {
            $d = Carbon::parse($c['expense_date']);
            abort_unless(
                $d->year === $report->year && $d->month === $report->month,
                422,
                "Date out of report month: {$c['expense_date']}"
            );
        }

        $result = DB::transaction(function () use ($data, $report) {
            $updated = [];
            foreach ($data['updates'] as $u) {
                $exp = Expense::find($u['id']);
                $exp->fill([
                    'station_from' => $u['station_from'] ?? null,
                    'station_to'   => $u['station_to'] ?? null,
                    'note'         => $u['note'] ?? null,
                    'cost'         => $u['cost'] ?? 0,
                    'trip_type'    => $u['trip_type'] ?? $exp->trip_type,
                    'seq'          => $u['seq'] ?? $exp->seq,
                ])->save();
                $updated[] = $exp->fresh();
            }

            $created = [];
            foreach ($data['creates'] as $c) {
                $created[] = Expense::create([
                    'expense_report_id' => $report->id,
                    'expense_date'      => $c['expense_date'],
                    'seq'               => $c['seq'],
                    'station_from'      => $c['station_from'] ?? null,
                    'station_to'        => $c['station_to'] ?? null,
                    'note'              => $c['note'] ?? null,
                    'cost'              => $c['cost'] ?? 0,
                    'trip_type'         => $c['trip_type'],
                    'category'          => $c['category'],
                ]);
            }

            return ['updated' => $updated, 'created' => $created];
        });

        return response()->json($result, 200);
    }

    /**
     * 経費明細を1行追加
     * POST /api/expenses — ロック済みレポートへの追加は 423 を返す
     */
    public function store(StoreExpenseRequest $req)
    {
        $data = $req->validated();

        $report = ExpenseReport::findOrFail($data['expense_report_id']);
        $this->authorize('update', $report);

        $this->abortIfLocked($report);

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

    /**
     * 経費明細を1行更新
     * PATCH /api/expenses/{expense} — ロック済みレポートへの変更は 423 を返す
     */
    public function update(UpdateExpenseRequest $req, Expense $expense)
    {
        $report = $expense->report;
        $this->authorize('update', $expense);

        $this->abortIfLocked($report);

        $data = $req->validated();

        if (!empty($data['commuter_pass_id'])) {
            $ok = CommuterPass::where('id', $data['commuter_pass_id'])->where('user_id', $report->user_id)->exists();
            abort_unless($ok, 422, 'Invalid commuter pass owner.');
        }

        $expense->fill($data)->save();

        return response()->json($expense->fresh());
    }

    /**
     * 経費明細を1行削除
     * DELETE /api/expenses/{expense} — ロック済みレポートへの削除は 423 を返す
     */
    public function destroy(Expense $expense)
    {
        $report = $expense->report;
        $this->authorize('delete', $expense);

        $this->abortIfLocked($report);

        $expense->delete();
        return response()->json(['ok' => true]);
    }
}
