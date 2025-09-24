<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ExpenseReport;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Services\Calendar\CalendarResolver;
use App\Services\Calendar\EventType;

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

        // ▼▼ ここから: 各日の EventType を CalendarResolver で集計 ▼▼
        /** @var \App\Services\Calendar\CalendarResolver $resolver */
        $resolver = app(\App\Services\Calendar\CalendarResolver::class);
        $start = \Carbon\Carbon::create($y, $m, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth();

        $events = $resolver->build($user, $start, $end);

        $daily = []; // 'YYYY-MM-DD' => ['has_on'=>bool, 'has_off_bg'=>bool]

        foreach ($events as $ev) {
            $dateKey = $ev['dateKey'] ?? substr((string)($ev['start'] ?? ''), 0, 10);
            if (!$dateKey) continue;

            $display     = strtolower((string)($ev['display'] ?? ''));
            $ext         = (array)($ev['extendedProps'] ?? []);
            $category    = strtolower((string)($ext['category'] ?? ''));
            $type        = strtolower((string)($ext['type'] ?? ($ev['type'] ?? '')));
            $planGroup   = strtolower((string)($ev['planGroup'] ?? ($ext['plan'] ?? '')));
            $classNames  = array_map('strval', (array)($ev['classNames'] ?? []));

            $daily[$dateKey] ??= ['has_on' => false, 'has_off_bg' => false];

            // --- ON 判定 ---
            // display が 'on' なら ON（REGULAR でも EVENT でもOK）
            // REGULAR かどうかは category が 'regular' だけでなく '5_regular' のような接尾/接頭も拾う
            $categoryIsRegular = ($category === 'regular' || str_contains($category, 'regular'));
            $isOn = ($display === 'on'); // REGULAR/EVENT 共通で on は on
            if ($isOn) {
                // ここで REGULAR / EVENT の細分けが必要なら category / planGroup で分岐可能
                $daily[$dateKey]['has_on'] = true;
            }

            // --- OFF（背景）判定 ---
            // ・祝日（fc-holiday or type=holiday）
            // ・会社所定/法定休（category が '1_off...' or fc-off-* クラス）
            // ※「有休（leave）」はこのデータには無いので今回は対象外
            $isHoliday = in_array('fc-holiday', $classNames, true) || $type === 'holiday';
            $isCompanyOff = str_starts_with($category, '1_off')
                || in_array('fc-off-prescribed', $classNames, true)
                || in_array('fc-off-statutory',  $classNames, true);

            if ($display === 'background' && ($isHoliday || $isCompanyOff)) {
                $daily[$dateKey]['has_off_bg'] = true;
            }
        }

        // === 最終色（ON優先、ONが無い日はOFF/背景があれば灰）===
        $eventOnMap = []; // 'YYYY-MM-DD' => bool (true=緑)
        foreach ($daily as $d => $flags) {
            if ($flags['has_on']) {
                // 例: 9/15 は祝日(OFF背景)だが on があるため緑
                $eventOnMap[$d] = true;
            } else {
                // on が無い日で背景OFF/祝日のみ → 灰
                $eventOnMap[$d] = false;
            }
        }
        // ▲▲ ここまで: $eventOnMap['YYYY-MM-DD'] = true(=ON) / false(=その他)

        return view('expenses.edit', [
            'user'        => $user,
            'report'      => $report,
            'y'           => $y,
            'm'           => $m,
            'rows'        => $rows,
            'eventOnMap'  => $eventOnMap, // ← Blade へ渡す
        ]);
    }
}
