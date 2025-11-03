<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\CommutingExpenses\RouteDeclarationService;

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
}
