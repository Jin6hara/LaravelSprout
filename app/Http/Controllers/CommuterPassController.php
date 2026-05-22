<?php

namespace App\Http\Controllers;

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

        return view('routes.registerPass', [
            'target'      => $target,
            'isAdminMode' => $isAdminMode,
        ]);
    }

    /**
     * 保存処理
     * - POST /commuter-passes             → 本人
     * - POST /commuter-passes/{user}      → admin が他人分
     */
    public function store(Request $request, ?User $user = null): RedirectResponse
    {
        $me = $request->user();
        $isAdminMode = $user !== null;
        $target      = $user ?: $me;
        $this->authorize('update', $target);

        $data = $this->validateRequest($request);

        // 対象ユーザーに紐づけて定期を登録
        $target->commuterPasses()->create([
            'date_from'   => $data['date_from'],
            'date_to'     => $data['date_to'],
            'station_from' => $data['station_from'],
            'station_to'  => $data['station_to'],
            'note'        => $data['note'] ?? null,
            'cost'        => $data['cost'] ?? 0,
        ]);

        return redirect()
            ->back()
            ->with('toast', 'Commuter pass has been registered.');
    }

    protected function validateRequest(Request $request): array
    {
        return $request->validate([
            'date_from'    => ['required', 'date'],
            'date_to'      => ['required', 'date', 'after_or_equal:date_from'],
            'station_from' => ['required', 'string', 'max:255'],
            'station_to'   => ['required', 'string', 'max:255'],
            'note'         => ['nullable', 'string'],
            'cost'         => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
