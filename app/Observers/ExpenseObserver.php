<?php

/**
 * 経費明細の保存・削除時に精算レポートの合計金額を再集計するオブザーバー。
 */
namespace App\Observers;

use App\Models\Expense;

class ExpenseObserver
{
    public function saved(Expense $expense): void
    {
        $this->recalcReportTotal($expense);
    }

    public function deleted(Expense $expense): void
    {
        $this->recalcReportTotal($expense);
    }

    private function recalcReportTotal(Expense $expense): void
    {
        if (!$expense->report) return;

        $sum = $expense->report->expenses()->sum('cost');
        $expense->report()->update(['total_amount' => (int)$sum]);
    }
}
