<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ExpensePolicy
{
    public function view(User $currentUser, Expense $expense): bool
    {
        return $currentUser->id === $expense->user_id || $currentUser->isAdmin();
    }

    public function update(User $currentUser, Expense $expense): bool
    {
        return $currentUser->id === $expense->user_id || $currentUser->isAdmin();
    }

    public function delete(User $currentUser, Expense $expense): bool
    {
        return $currentUser->id === $expense->user_id || $currentUser->isAdmin();
    }

    // 作成は「対象レポートの所有者か？」で間接判定するのが安全
    public function create(User $currentUser): bool
    {
        return $currentUser->isAdmin() || true; // 必要なら true→false に調整
    }
}
