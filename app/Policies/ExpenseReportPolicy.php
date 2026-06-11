<?php

/**
 * 経費精算レポートの閲覧・更新・提出に対するアクセス制御を定義するポリシー。
 */
namespace App\Policies;

use App\Models\ExpenseReport;
use App\Models\User;
use App\Services\CurrentScopeService;

class ExpenseReportPolicy
{
    public function view(User $actor, ExpenseReport $report): bool
    {
        return $actor->id === $report->user_id || $this->isScopedAdminFor($actor, $report);
    }

    public function update(User $actor, ExpenseReport $report): bool
    {
        return $actor->id === $report->user_id || $this->isScopedAdminFor($actor, $report);
    }

    public function submit(User $actor, ExpenseReport $report): bool
    {
        return $actor->id === $report->user_id || $this->isScopedAdminFor($actor, $report);
    }

    public function unsubmit(User $actor, ExpenseReport $report): bool
    {
        return $this->isScopedAdminFor($actor, $report);
    }

    public function manage(User $actor): bool
    {
        return $actor->isAdmin();
    }

    private function isScopedAdminFor(User $actor, ExpenseReport $report): bool
    {
        return $actor->isAdmin()
            && in_array($report->user_id, app(CurrentScopeService::class)->targetUserIds(), true);
    }
}
