<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CurrentScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\CommutingExpenses\RouteDeclarationService;
use Illuminate\Http\RedirectResponse;
use App\Models\RouteDeclaration;
use App\Models\EmploymentTerm;
use App\Models\Schedule;

class RouteDeclarationController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    public function index(Request $request, RouteDeclarationService $svc)
    {
        $viewer = Auth::user();

        $targetUser = $viewer;
        if ($viewer->hasRole(['admin', 'super_admin']) && $request->filled('user_id')) {
            $targetUser = $this->scopeService->targetUserQuery()->findOrFail($request->query('user_id'));
        }

        $declarations = $svc->allForUser($targetUser);

        return view('routes.index', [
            'declarations' => $declarations,
            'viewer'       => $viewer,
            'targetUser'   => ($targetUser->is($viewer) ? null : $targetUser),
        ]);
    }

    public function showUser(User $user, RouteDeclarationService $svc)
    {
        $viewer = Auth::user();
        if (!$viewer->hasRole(['admin', 'super_admin'])) abort(403);
        if (!in_array($user->id, $this->scopeService->targetUserIds())) abort(404);

        $declarations = $svc->allForUser($user);

        return view('routes.index', [
            'declarations' => $declarations,
            'viewer'       => $viewer,
            'targetUser'   => $user,
        ]);
    }

    /**
     * 新規作成画面
     * - /routes/create           → $user = null（本人）
     * - /routes/{user}/create    → $user = 該当ユーザー（admin 用）
     */
    public function create(Request $request, ?User $user = null)
    {
        $me = $request->user();

        // admin 経由かどうか
        $isAdminMode = $user !== null;
        $target      = $user ?: $me;

        if ($isAdminMode && !$me->hasAnyRole(['admin', 'super_admin'])) {
            abort(403);
        }

        if ($isAdminMode && !in_array($target->id, $this->scopeService->targetUserIds())) {
            abort(404);
        }

        return view('routes.create', [
            'target'      => $target,      // 申告対象ユーザー
            'isAdminMode' => $isAdminMode, // 管理者モードかどうか
        ]);
    }

    /**
     * 保存処理
     * - POST /routes             → 本人
     * - POST /routes/{user}      → admin が他人分
     */
    public function store(Request $request, ?User $user = null): RedirectResponse
    {
        $me = $request->user();
        $isAdminMode = $user !== null;
        $target      = $user ?: $me;

        if ($isAdminMode && !$me->hasAnyRole(['admin', 'super_admin'])) {
            abort(403);
        }

        if ($isAdminMode && !in_array($target->id, $this->scopeService->targetUserIds())) {
            abort(404);
        }

        $data = $this->validateRequest($request);

        // route_declarations 保存
        $declaration = $target->routeDeclarations()->create([
            'submitted_at'    => now(),
            'closest_station' => $data['closest_station'],
            'train_line'      => $data['train_line'] ?? null,
            'effective_date'  => $data['effective_date'],
            'reason'          => $data['reason'] ?? null,
        ]);

        // route_details 複数行を一括保存
        foreach ($data['details'] as $detail) {
            $declaration->details()->create($detail);
        }

        return redirect()
            ->back()
            ->with('toast', 'Route declaration has been saved.');
    }

    protected function validateRequest(Request $request): array
    {
        return $request->validate([
            'closest_station' => ['required', 'string', 'max:255'],
            'train_line'      => ['nullable', 'string', 'max:255'],
            'effective_date'  => ['required', 'date'],
            'reason'          => ['nullable', 'string'],

            'details'                => ['required', 'array', 'min:1'],
            'details.*.dow'          => ['required', 'in:Mon,Tue,Wed,Thu,Fri,Sat,Sun'],
            'details.*.from_station' => ['required', 'string', 'max:255'],
            'details.*.to_station'   => ['required', 'string', 'max:255'],
            'details.*.trip_type'    => ['required', 'in:round_trip,one_way'],
            'details.*.amount'       => ['required', 'integer', 'min:0'],
            'details.*.route_text'   => ['nullable', 'string'],
            'details.*.note'         => ['nullable', 'string'],
        ]);
    }

    /**
     * ルート申告の提出状況レポート（管理者）
     *
     * 条件：
     *  - active_on 日時点で在籍 or スケジュール有りの人を対象
     *  - 各ユーザーの最新 RouteDeclaration を取得
     */
    public function report(Request $request)
    {
        // active_on 日付（デフォルト：今日）
        $activeOn = $request->date('active_on')
            ? $request->date('active_on')->format('Y-m-d')
            : now()->toDateString();

        // mode: schedule / employment / user
        $mode = $request->input('mode', 'schedule'); // デフォルト: active Schedule only

        // Specific user 用
        $selectedUserId = null;
        if ($mode === 'user') {
            $selectedUserId = $request->integer('user_id') ?: null;
        }

        // 管理スコープ内の user_id に絞り込む
        $scopedUserIds = $this->scopeService->targetUserIds();

        // 対象ユーザーの id 集合
        $userIds = collect();

        if ($mode === 'schedule') {
            // Users with active Schedule only
            $userIds = Schedule::query()
                ->where('is_active', true)
                ->where('effective_start', '<=', $activeOn)
                ->where('effective_end', '>=', $activeOn)
                ->pluck('user_id')
                ->unique()
                ->values();
        } elseif ($mode === 'employment') {
            // Employees with active Employment Term only
            $userIds = EmploymentTerm::query()
                ->where('start_date', '<=', $activeOn)
                ->where(function ($q) use ($activeOn) {
                    $q->whereNull('end_date')
                        ->orWhere('end_date', '>=', $activeOn);
                })
                ->pluck('user_id')
                ->unique()
                ->values();
        } elseif ($mode === 'user') {
            // Specific user
            if ($selectedUserId) {
                $userIds = collect([$selectedUserId]);
            } else {
                $userIds = collect(); // ユーザー未選択なら空
            }
        }

        // 管理スコープ外の user_id を除外
        if ($userIds->isNotEmpty()) {
            $userIds = $userIds->intersect($scopedUserIds)->values();
        }

        // 対象ユーザー本体
        $users = $userIds->isNotEmpty()
            ? User::query()
            ->whereIn('id', $userIds)
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'first_name', 'family_name'])
            : collect();

        // 各ユーザーの最新 RouteDeclaration
        $declarations = $userIds->isNotEmpty()
            ? RouteDeclaration::query()
            ->whereIn('user_id', $userIds)
            ->select('id', 'user_id', 'submitted_at', 'closest_station', 'effective_date')
            ->orderBy('user_id')
            ->orderByDesc('submitted_at')
            ->get()
            : collect();

        $latestByUser = $declarations
            ->groupBy('user_id')
            ->map(fn($group) => $group->first());

        $rows = [];
        $countSubmitted    = 0;
        $countNotSubmitted = 0;

        foreach ($users as $user) {
            $dec = $latestByUser->get($user->id);

            if ($dec) {
                $status        = 'Submitted';
                $submittedAt   = optional($dec->submitted_at)->format('Y-m-d H:i');
                $effectiveDate = optional($dec->effective_date)->format('Y-m-d');
                $closest       = $dec->closest_station;
                $countSubmitted++;
            } else {
                $status        = 'Not Submitted';
                $submittedAt   = null;
                $effectiveDate = null;
                $closest       = null;
                $countNotSubmitted++;
            }

            $rows[] = [
                'employee_code'   => $user->employee_code,
                // 表示名: first_name, family_name, employee_code
                'display_name'    => trim($user->first_name . ' ' . $user->family_name) .
                    ' [' . $user->employee_code . ']',
                'submitted_at'    => $submittedAt,
                'effective_date'  => $effectiveDate,
                'closest_station' => $closest,
                'status'          => $status,
            ];
        }

        $summary = [
            'total_users'   => $users->count(),
            'submitted'     => $countSubmitted,
            'not_submitted' => $countNotSubmitted,
            'shown_rows'    => count($rows),
        ];

        // Specific user 用の選択肢
        $allUsers = $this->scopeService->targetUserQuery()
            ->orderBy('employee_code')
            ->get(['id', 'employee_code', 'first_name', 'family_name']);

        return view('routes.declarationReport', [
            'rows'            => $rows,
            'summary'         => $summary,
            'activeOn'        => $activeOn,
            'mode'            => $mode,
            'selectedUserId'  => $selectedUserId,
            'allUsers'        => $allUsers,
        ]);
    }
}
