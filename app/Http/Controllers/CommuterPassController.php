<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommuterPass\StoreCommuterPassRequest;
use App\Models\CommuterPass;
use App\Models\User;
use App\Services\CommutingExpenses\CommuterPassWriteService;
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
            $selectedPass = $target->commuterPasses()
                ->findOrFail($request->integer('pass'));
            $this->authorize('update', $selectedPass);
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
    public function store(StoreCommuterPassRequest $request, CommuterPassWriteService $writer, ?User $user = null): RedirectResponse
    {
        $target = $user ?: $request->user();
        $this->authorize('update', $target);

        $writer->create($target, $request->validated());

        return redirect()->back()->with('toast', 'Commuter pass has been registered.');
    }

    /**
     * 更新処理
     * - PUT /commuter-passes/{pass} → 本人/管理者共通
     */
    public function update(StoreCommuterPassRequest $request, CommuterPass $pass, CommuterPassWriteService $writer): RedirectResponse
    {
        $target = $pass->user()->firstOrFail();
        $this->authorize('update', $pass);

        $writer->update($pass, $request->validated());

        return redirect()->back()->with('toast', 'Commuter pass has been updated.');
    }

    /**
     * 削除処理
     * - DELETE /commuter-passes/{pass} → Admin以上のみ
     */
    public function destroy(Request $request, CommuterPass $pass): RedirectResponse
    {
        $target = $pass->user()->firstOrFail();
        $this->authorize('delete', $pass);

        $pass->delete();

        $redirectUrl = $request->user()->is($target)
            ? route('commuter_passes.create')
            : route('commuter_passes.admin.create', ['user' => $target]);

        return redirect($redirectUrl)->with('toast', 'Commuter pass has been deleted.');
    }
}
