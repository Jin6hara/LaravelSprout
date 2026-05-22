<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Services\CurrentScopeService;

class ExpensePolicy
{
    public function update(User $actor, Expense $expense): bool
    {
        return $this->canManage($actor, $expense);
    }

    public function delete(User $actor, Expense $expense): bool
    {
        return $this->canManage($actor, $expense);
    }

    private function canManage(User $actor, Expense $expense): bool
    {
        $report = $expense->report;

        if (! $report) {
            return false;
        }

        return $actor->id === $report->user_id
            || (
                $actor->isAdmin()
                && in_array($report->user_id, app(CurrentScopeService::class)->targetUserIds(), true)
            );
    }
}
