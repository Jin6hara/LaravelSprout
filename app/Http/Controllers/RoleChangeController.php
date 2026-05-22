<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleChange\ApplyRoleChangeRequest;
use App\Models\User;
use App\Services\RoleChange\RoleChangeApplicationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RoleChangeController extends Controller
{
    public function __construct(private RoleChangeApplicationService $roleChangeService) {}

    public function show(User $user): View
    {
        $currentRole    = $user->getRoleNames()->first();
        $availableRoles = ['general', 'admin', 'super_admin'];

        return view('user.roleChange', compact('user', 'currentRole', 'availableRoles'));
    }

    public function apply(ApplyRoleChangeRequest $request, User $user)
    {
        $this->authorize('applyRoleChange', $user);

        $data = $request->validated();

        $this->roleChangeService->apply($user, Auth::user(), $data['role'], $data['reason']);

        return back()->with('toast', '権限変更の申請を受け付けました。');
    }
}
