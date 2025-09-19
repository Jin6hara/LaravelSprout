<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ExpenseReport;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ExpenseEditController extends Controller
{
    // 本人用 /expenses/edit
    public function selfEdit(Request $req)
    {
        $user = $req->user();
        return $this->renderFor($user, $req);
    }

    // 管理者用 /expenses/{user}/edit
    public function adminEdit(User $user, Request $req)
    {
        // ここは運用に合わせて調整: 例) isAdmin() 判定
        if (!method_exists($req->user(), 'isAdmin') || !$req->user()->isAdmin()) {
            abort(403);
        }
        return $this->renderFor($user, $req);
    }

    private function renderFor(User $user, Request $req)
    {
        $y = (int)($req->query('year')  ?? now()->year);
        $m = (int)($req->query('month') ?? now()->month);

        $report = ExpenseReport::query()
            ->where('user_id', $user->id)
            ->where('year', $y)->where('month', $m)
            ->firstOrFail();

        $expenses = $report->expenses()
            ->orderBy('expense_date')
            ->get(['id', 'expense_date', 'station_from', 'station_to', 'note', 'cost', 'trip_type', 'category', 'commuter_pass_id', 'created_at', 'updated_at']);

        // Tabulatorに渡しやすい配列へ（Enum/Carbonをstringへ）
        $rows = $expenses->map(function ($e) {
            return [
                'id'               => $e->id,
                'expense_date'     => $e->expense_date?->toDateString(),
                'station_from'     => $e->station_from,
                'station_to'       => $e->station_to,
                'note'             => $e->note,
                'cost'             => (int)$e->cost,
                'trip_type'        => is_object($e->trip_type) ? $e->trip_type->value : $e->trip_type,
                'category'         => is_object($e->category) ? $e->category->value  : $e->category,
                'commuter_pass_id' => $e->commuter_pass_id,
            ];
        })->values();

        return view('expenses.edit', [
            'user'    => $user,
            'report'  => $report,
            'y'       => $y,
            'm'       => $m,
            'rows'    => $rows,
        ]);
    }
}
