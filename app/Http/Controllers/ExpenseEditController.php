<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ExpenseReport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\Calendar\CalendarResolver;
use App\Services\Calendar\EventType;
use App\Enums\ExpenseReportStatus;

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
        $m = max(1, min(12, $m));

        $report = ExpenseReport::query()
            ->where('user_id', $user->id)
            ->where('year', $y)->where('month', $m)
            ->first(); // ★ 404にしない

        $expenses = $report
            ? $report->expenses()
            ->orderBy('expense_date')
            ->get(['id', 'expense_date', 'seq', 'station_from', 'station_to', 'note', 'cost', 'trip_type', 'category', 'commuter_pass_id', 'created_at', 'updated_at'])
            : collect(); // ★ 無ければ空

        $rows = $expenses->map(function ($e) {
            return [
                'id'               => $e->id,
                'expense_date'     => $e->expense_date?->toDateString(),
                'seq'              => $e->seq,
                'station_from'     => $e->station_from,
                'station_to'       => $e->station_to,
                'note'             => $e->note,
                'cost'             => (int)$e->cost,
                'trip_type'        => is_object($e->trip_type) ? $e->trip_type->value : $e->trip_type,
                'category'         => is_object($e->category) ? $e->category->value  : $e->category,
                'commuter_pass_id' => $e->commuter_pass_id,
            ];
        })->values();

        // ▼ CalendarResolver は月に対して常に実行（ON/OFF着色用）
        /** @var \App\Services\Calendar\CalendarResolver $resolver */
        $resolver = app(\App\Services\Calendar\CalendarResolver::class);
        $start = \Carbon\Carbon::create($y, $m, 1, 0, 0, 0, 'Asia/Tokyo')->startOfDay();
        $end   = (clone $start)->endOfMonth();
        $events = $resolver->build($user, $start, $end);

        $daily = [];
        foreach ($events as $ev) {
            $dateKey = $ev['dateKey'] ?? (isset($ev['start'])
                ? (is_string($ev['start']) ? substr($ev['start'], 0, 10)
                    : (\Carbon\Carbon::parse($ev['start'])->toDateString()))
                : null);
            if (!$dateKey) continue;

            $display    = strtolower((string)($ev['display'] ?? ''));
            $ext        = (array)($ev['extendedProps'] ?? []);
            $category   = strtolower((string)($ext['category'] ?? ''));
            $type       = strtolower((string)($ext['type'] ?? ($ev['type'] ?? '')));
            $classNames = array_map('strval', (array)($ev['classNames'] ?? []));

            $daily[$dateKey] ??= ['has_on' => false, 'has_off_bg' => false];

            if ($display === 'on') $daily[$dateKey]['has_on'] = true;

            $hasClass = fn(string $needle) => in_array($needle, $classNames, true);
            $isHoliday    = $hasClass('fc-holiday') || $type === 'holiday';
            $isCompanyOff = str_starts_with($category, '1_off')
                || $hasClass('fc-off-prescribed')
                || $hasClass('fc-off-statutory')
                || $type === 'company_off';

            if ($display === 'background' && ($isHoliday || $isCompanyOff)) {
                $daily[$dateKey]['has_off_bg'] = true;
            }
        }
        $eventOnMap = [];
        foreach ($daily as $d => $flags) $eventOnMap[$d] = $flags['has_on'];

        return view('expenses.edit', [
            'user'        => $user,
            'report'      => $report,              // ★ null 可
            'hasReport'   => (bool)$report,        // ★ 追加
            'y'           => $y,
            'm'           => $m,
            'rows'        => $rows,
            'eventOnMap'  => $eventOnMap,
        ]);
    }

    public function submit(ExpenseReport $report, Request $request)
    {
        // 認可：本人 or 管理者（Policy推奨）
        $user = $request->user();
        $authorized = $user->id === $report->user_id || (method_exists($user, 'isAdmin') && $user->isAdmin());
        abort_unless($authorized, 403);

        // 既に提出済みなら何もしない（冪等）
        if ($report->status !== ExpenseReportStatus::SUBMITTED->value) {
            $report->status = ExpenseReportStatus::SUBMITTED->value;
            $report->submitted_at = now('Asia/Tokyo');
            $report->save();
        }

        // 年月に戻る（本人 or 管理者で分岐）
        $y = $report->year;
        $m = $report->month;

        if ($user->id === $report->user_id) {
            return redirect()->route('expenses.edit', ['year' => $y, 'month' => $m])
                ->with('status', '提出しました。編集はできません。');
        } else {
            return redirect()->route('expenses.admin.edit', ['user' => $report->employee_code, 'year' => $y, 'month' => $m])
                ->with('status', '提出しました。編集はできません。');
        }
    }
}
