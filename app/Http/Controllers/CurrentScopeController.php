<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurrentScope\StoreCurrentScopeRequest;
use App\Models\UserManagementScope;

class CurrentScopeController extends Controller
{
    public function store(StoreCurrentScopeRequest $request)
    {
        $scopeId = $request->validated()['scope_id'];
        $scope   = UserManagementScope::findOrFail($scopeId);
        $this->authorize('select', $scope);

        $request->session()->put('selected_scope_id', $scopeId);

        return redirect()->back();
    }
}
