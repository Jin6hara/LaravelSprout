<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RouteDeclaration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RouteDeclarationController extends Controller
{
    /**
     * 一般ユーザー：自分の申告すべて
     * 管理者：?user_id= 指定時はそのユーザーの申告すべて / 未指定なら自分
     */
    public function index(Request $request)
    {
        $viewer = Auth::user();

        // 対象ユーザーの決定
        $targetUser = $viewer;
        if ($viewer->hasRole(['admin', 'super_admin']) && $request->filled('user_id')) {
            $targetUser = User::query()->findOrFail($request->query('user_id'));
        }

        // 対象ユーザーの申告をすべて取得（effective_date 降順）
        $declarations = RouteDeclaration::query()
            ->where('user_id', $targetUser->id)
            ->with([
                'user.commuterPasses',
                'details' => function ($q) {
                    // DOW を Sun → Sat 順に
                    $q->orderByRaw("FIELD(dow,'Sun','Mon','Tue','Wed','Thu','Fri','Sat')");
                },
            ])
            ->orderByDesc('effective_date')
            ->get();

        return view('routes.index', [
            'declarations' => $declarations,
            'viewer'       => $viewer,
            'targetUser'   => ($targetUser->is($viewer) ? null : $targetUser),
        ]);
    }

    /**
     * 管理者：特定ユーザーの申告すべて
     */
    public function showUser(User $user)
    {
        $viewer = Auth::user();
        if (!$viewer->hasRole(['admin', 'super_admin'])) {
            abort(403);
        }

        $declarations = RouteDeclaration::query()
            ->where('user_id', $user->id)
            ->with([
                'user.commuterPasses',
                'details' => function ($q) {
                    $q->orderByRaw("FIELD(dow,'Sun','Mon','Tue','Wed','Thu','Fri','Sat')");
                },
            ])
            ->orderByDesc('effective_date')
            ->get();

        return view('routes.index', [
            'declarations' => $declarations,
            'viewer'       => $viewer,
            'targetUser'   => $user,
        ]);
    }
}
