<?php
/**
 * ログインユーザーが操作対象スコープ（地区・部署）をセッションに保存する処理を担うコントローラ。
 */
namespace App\Http\Controllers;

use App\Http\Requests\CurrentScope\StoreCurrentScopeRequest;
use App\Models\UserManagementScope;

class CurrentScopeController extends Controller
{
    /**
     * 選択スコープをセッションに保存
     * POST /current-scope — ログインユーザーに紐づく scope_id のみ許可
     */
    public function store(StoreCurrentScopeRequest $request)
    {
        $scopeId = $request->validated()['scope_id'];
        $scope   = UserManagementScope::findOrFail($scopeId);
        $this->authorize('select', $scope);

        $request->session()->put('selected_scope_id', $scopeId);

        return redirect()->back();
    }
}
