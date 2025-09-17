<?php

namespace App\Observers;

use App\Enums\ExpenseReportStatus;
use App\Models\ExpenseReport;

class ExpenseReportObserver
{
    public function updating(ExpenseReport $report): void
    {
        // 提出日時が入ったら status=submitted（承認/支払は別途フローで上書き）
        if ($report->isDirty('submitted_at') && $report->submitted_at && $report->status !== ExpenseReportStatus::SUBMITTED) {
            $report->status = ExpenseReportStatus::SUBMITTED;
        }
    }
}
