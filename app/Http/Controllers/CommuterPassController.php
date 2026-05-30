<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommuterPass\StoreCommuterPassRequest;
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

        $passHistory = $target->commuterPasses()
            ->orderByDesc('date_from')
            ->orderByDesc('id')
            ->get();

        return view('routes.registerPass', [
            'target'      => $target,
            'isAdminMode' => $isAdminMode,
            'passHistory' => $passHistory,
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
}
