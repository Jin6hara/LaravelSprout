<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommuterPass\StoreCommuterPassRequest;
use App\Models\CommuterPass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class CommuterPassController extends Controller
{
    /**
     * 定期券新規登録画面
     * - GET /commuter-passes/create           → 本人
     * - GET /commuter-passes/{user}/create    → admin が他人分
     */
    public function create(Request $request, ?User $user = null)
    {
        $me = $request->user();

        $isAdminMode = $user !== null;
        $target      = $user ?: $me;
        $this->authorize('view', $target);

        $canManagePasses = $me?->hasAnyRole(['admin', 'super_admin']) ?? false;

        $passHistory = $target->commuterPasses()
            ->orderByDesc('date_from')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $selectedPass = null;
        if ($request->filled('pass')) {
            abort_unless($canManagePasses, 403);

            $selectedPass = $target->commuterPasses()
                ->findOrFail($request->integer('pass'));
        }

        return view('routes.registerPass', [
            'target'      => $target,
            'isAdminMode' => $isAdminMode,
            'passHistory' => $passHistory,
            'selectedPass' => $selectedPass,
            'canManagePasses' => $canManagePasses,
        ]);
    }

    /**
     * 保存処理
     * - POST /commuter-passes             → 本人
     * - POST /commuter-passes/{user}      → admin が他人分
     */
    public function store(StoreCommuterPassRequest $request, ?User $user = null): RedirectResponse
    {
        $target = $user ?: $request->user();
        $this->authorize('update', $target);

        $data = $request->validated();

        // 対象ユーザーに紐づけて定期を登録
        $target->commuterPasses()->create([
            'date_from'    => $data['date_from'],
            'date_to'      => $data['date_to'],
            'station_from' => $data['station_from'],
            'station_to'   => $data['station_to'],
            'note'         => $data['note'] ?? null,
            'cost'         => $data['cost'] ?? 0,
        ]);

        return redirect()->back()->with('toast', 'Commuter pass has been registered.');
    }

    /**
     * 更新処理
     * - PUT /commuter-passes/{pass} → 本人/管理者共通
     */
    public function update(StoreCommuterPassRequest $request, CommuterPass $pass): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'super_admin']), 403);

        $target = $pass->user()->firstOrFail();
        $this->authorize('update', $target);

        $data = $request->validated();

        $pass->update([
            'date_from'    => $data['date_from'],
            'date_to'      => $data['date_to'],
            'station_from' => $data['station_from'],
            'station_to'   => $data['station_to'],
            'note'         => $data['note'] ?? null,
            'cost'         => $data['cost'] ?? 0,
        ]);

        return redirect()->back()->with('toast', 'Commuter pass has been updated.');
    }

    /**
     * 削除処理
     * - DELETE /commuter-passes/{pass} → Admin以上のみ
     */
    public function destroy(Request $request, CommuterPass $pass): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['admin', 'super_admin']), 403);

        $target = $pass->user()->firstOrFail();
        $this->authorize('update', $target);

        $pass->delete();

        $redirectUrl = $request->user()->is($target)
            ? route('commuter_passes.create')
            : route('commuter_passes.admin.create', ['user' => $target]);

        return redirect($redirectUrl)->with('toast', 'Commuter pass has been deleted.');
    }
}
