<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RouteDeclaration;
use Illuminate\Support\Facades\Auth;

class RouteDeclarationController extends Controller
{
    /**
     * 一般ユーザー：自分の申告だけ表示
     * 管理者：全員分一覧（任意）
     */
    public function index()
    {
        $viewer = Auth::user();

        $query = RouteDeclaration::query()
            ->with(['user.commuterPasses', 'details'])  // ← ここを変更
            ->orderByDesc('effective_date');

        if (!$viewer->hasRole(['admin', 'super_admin'])) {
            $query->where('user_id', $viewer->id);
        }

        $declarations = $query->get();

        // $commuters は不要になったので渡さない
        return view('routes.index', compact('declarations', 'viewer'));
    }

    public function showUser(User $user)
    {
        $declarations = RouteDeclaration::query()
            ->with(['user.commuterPasses', 'details'])  // ← 同上
            ->where('user_id', $user->id)
            ->orderByDesc('effective_date')
            ->get();

        return view('routes.index', [
            'declarations' => $declarations,
            'viewer'       => Auth::user(),
            'targetUser'   => $user,
        ]);
    }
}
