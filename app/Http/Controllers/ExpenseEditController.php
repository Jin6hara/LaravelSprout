<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ExpenseReport;
use App\Services\CommutingExpenses\RouteDeclarationService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Enums\ExpenseReportStatus;
use Illuminate\Support\Facades\Artisan;

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
        $this->authorize('view', $user);
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

        // ▼ CommuterPass：この月に重なる定期券を取得し、日付キーの「有効マップ」を作る
        $passes = \App\Models\CommuterPass::query()
            ->where('user_id', $user->id)
            ->whereDate('date_from', '<=', $end->toDateString())
            ->whereDate('date_to',   '>=', $start->toDateString())
            ->orderBy('date_from')
            ->get(['id', 'date_from', 'date_to', 'station_from', 'station_to']);

        $passActiveMap = [];
        foreach ($passes as $p) {
            $from = \Carbon\Carbon::parse($p->date_from)->max($start);
            $to   = \Carbon\Carbon::parse($p->date_to)->min($end);
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                $passActiveMap[$d->toDateString()] = true;
            }
        }

        // ▼ 画面表示用に整形（当月区間とフルの両方を持たせる）
        $activePasses = $passes->map(function ($p) use ($start, $end) {
            $fromFull = \Carbon\Carbon::parse($p->date_from);
            $toFull   = \Carbon\Carbon::parse($p->date_to);

            $fromDisp = $fromFull->copy()->max($start);
            $toDisp   = $toFull->copy()->min($end);

            return [
                'id'            => $p->id,
                'station_from'  => $p->station_from,
                'station_to'    => $p->station_to,
                'cost'          => (int) $p->cost,

                // 定期券本来の期間（YYYY-MM-DD形式）
                'valid_from'    => $fromFull->toDateString(),
                'valid_to'      => $toFull->toDateString(),
                'valid_range'   => $fromFull->toDateString() . '〜' . $toFull->toDateString(),

                // 当月に表示する期間（YYYY-MM-DD形式）
                'display_from'  => $fromDisp->toDateString(),
                'display_to'    => $toDisp->toDateString(),
                'display_range' => $fromDisp->toDateString() . '〜' . $toDisp->toDateString(),
                //'valid_range'   => $fromFull->translatedFormat('n月j日').'〜'.$toFull->translatedFormat('n月j日'),
            ];
        })->values()->all();

        // ユーザーの最新ルート宣言を1件取得（月単位）
        $end = $end ?? now('Asia/Tokyo')->endOfDay(); //end が null の場合その日の 23:59:59 に丸める
        $svc = app(RouteDeclarationService::class);
        $routeDecl = $svc->latestUntil($user, $end);

        return view('expenses.edit', [
            'user'          => $user,
            'report'        => $report,              // ★ null 可
            'hasReport'     => (bool)$report,        // ★ 提出済み or 下書き有り
            'y'             => $y,                   // 選択中の年
            'm'             => $m,                   // 選択中の月
            'rows'          => $rows,                // 明細行の配列
            'eventOnMap'    => $eventOnMap,          // ★ イベントON日のマップ 
            'passActiveMap' => $passActiveMap,       // ★ 定期券の有効日マップ
            'activePasses'  => $activePasses,        // ★ ヘッダー表示用
            'routeDecl'     => $routeDecl,           // ★ RouteDeclaration
        ]);
    }

    public function submit(ExpenseReport $report, Request $request)
    {
        $this->authorize('submit', $report);
        $user = $request->user();

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
                ->with('toast', 'submitted. You can no longer edit the report.');
        } else {
            return redirect()->route('expenses.admin.edit', ['user' => $report->employee_code, 'year' => $y, 'month' => $m])
                ->with('toast', 'submitted. The user can no longer edit the report.');
        }
    }

    public function unsubmit(Request $request, ExpenseReport $report)
    {
        $this->authorize('unsubmit', $report);

        // ここは「SUBMITTED のときだけ戻せる」などルールを入れてもOK
        if ($report->status !== ExpenseReportStatus::SUBMITTED->value) {
            return back()->with('error', 'Only submitted reports can be unsubmitted.');
        }

        $report->update([
            'submitted_at' => null,
            'status'       => ExpenseReportStatus::DRAFT,
        ]);

        return back()->with('toast', 'Expense report has been returned to draft.');
    }

    /**
     * 対象年月の当月分 expense_reports と expenses を生成
     * Blade から手動実行する用
     */
    public function generateMonthly(Request $request)
    {
        $this->authorize('manage', ExpenseReport::class);

        // 画面側から送ってくる year/month（なければ今月）
        $year  = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        // 既存の Artisan コマンドをそのまま利用
        Artisan::call('expenses:generate-monthly', [
            'year'  => $year,
            'month' => $month,
        ]);

        return back()->with(
            'toast',
            sprintf('Expense reports for %04d-%02d have been generated.', $year, $month)
        );
    }

    /**
     * 対象年月の「空の expense（note=null & cost=0）」を削除
     * Blade から手動実行する用
     */
    public function cleanupEmpty(Request $request)
    {
        $this->authorize('manage', ExpenseReport::class);

        // 画面から year/month が送られてきたらそれを使う
        // 何も無ければ「今から2ヶ月前」を対象にする
        if ($request->filled('year') && $request->filled('month')) {
            $year  = (int) $request->input('year');
            $month = (int) $request->input('month');
        } else {
            $target = now()->subMonthsNoOverflow(2);
            $year   = (int) $target->year;
            $month  = (int) $target->month;
        }

        // 既存の Artisan コマンドをそのまま利用
        Artisan::call('expenses:cleanup-empty', [
            'year'  => $year,
            'month' => $month,
        ]);

        // コマンドの出力を取得
        $output = trim(Artisan::output());

        // 最後の行だけ抜き出す（例: "Deleted 12 expense rows."）
        $lines = preg_split('/\r\n|\r|\n/', $output);
        $lastLine = $lines ? trim(end($lines)) : null;

        // フォールバック（何も取れなかった場合用）
        $toast = $lastLine ?: sprintf('Cleanup finished for %04d-%02d.', $year, $month);

        return back()->with('toast', $toast);
    }
}
