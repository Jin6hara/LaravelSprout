<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleChange\ApplyRoleChangeRequest;
use App\Models\Department;
use App\Models\District;
use App\Models\User;
use App\Services\RoleChange\RoleChangeApplicationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RoleChangeController extends Controller
{
    public function __construct(private RoleChangeApplicationService $roleChangeService) {}

    /**
     * ロール変更申請フォーム表示
     * GET /{user}/role-change
     */
    public function show(User $user): View
    {
        $currentRole    = $user->getRoleNames()->first();
        $availableRoles = ['general', 'admin', 'super_admin'];

        $districts   = District::whereNull('parent_id')->with('children')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        $user->load(['district', 'department', 'managementScopes.district', 'managementScopes.department']);

        return view('user.roleChange', compact('user', 'currentRole', 'availableRoles', 'districts', 'departments'));
    }

    /**
     * 権限変更申請の送信
     * POST /users/{user}/role-change/apply
     */
    public function apply(ApplyRoleChangeRequest $request, User $user)
    {
        $this->authorize('applyRoleChange', $user);

        $data = $request->validated();

        $this->roleChangeService->apply(
            $user,
            Auth::user(),
            $data['role'],
            $data['reason'],
            (int) $data['district_id'],
            (int) $data['department_id'],
            $data['scopes'] ?? [],
        );

        return back()->with('toast', '権限変更の申請を受け付けました。');
    }
}
