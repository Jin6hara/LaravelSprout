<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\CommutingExpenses\RouteDeclarationService;
use Illuminate\Http\RedirectResponse;

class RouteDeclarationController extends Controller
{
    public function index(Request $request, RouteDeclarationService $svc)
    {
        $viewer = Auth::user();

        $targetUser = $viewer;
        if ($viewer->hasRole(['admin', 'super_admin']) && $request->filled('user_id')) {
            $targetUser = User::query()->findOrFail($request->query('user_id'));
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
}
