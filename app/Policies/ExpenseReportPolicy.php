<?php

namespace App\Policies;

use App\Models\ExpenseReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExpenseReportPolicy
{
    // 一覧系（管理画面）: 管理者のみ
    public function viewAny(User $currentUser): bool
    {
        return $currentUser->isAdmin();
    }

    // 個票閲覧: 自分 or 管理者
    public function view(User $currentUser, ExpenseReport $report): bool
    {
        return $currentUser->id === $report->user_id || $currentUser->isAdmin();
    }

    // 個票更新: 自分 or 管理者
    public function update(User $currentUser, ExpenseReport $report): bool
    {
        return $currentUser->id === $report->user_id || $currentUser->isAdmin();
    }

    // 削除が必要なら
    public function delete(User $currentUser, ExpenseReport $report): bool
    {
        return $currentUser->isAdmin();
    }
}
