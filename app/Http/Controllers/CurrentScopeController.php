<?php

namespace App\Http\Controllers;

use App\Models\UserManagementScope;
use Illuminate\Http\Request;

class CurrentScopeController extends Controller
{
    /**
     * 選択された scope_id をセッションに保存する。
     *
     * POST /current-scope
     *
     * ログインユーザーに紐づく user_management_scopes.id であることを検証し、
     * 問題なければ session に保存して元の画面へ戻る。
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'scope_id' => ['required', 'integer'],
        ]);

        $scope = UserManagementScope::findOrFail($validated['scope_id']);
        $this->authorize('select', $scope);

        $request->session()->put('selected_scope_id', $validated['scope_id']);

        return redirect()->back();
    }
}
