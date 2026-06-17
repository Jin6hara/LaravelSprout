<?php
/**
 * ユーザーの通勤ルート（定期券・経路パターン）一覧画面の表示を担うコントローラ。
 */
namespace App\Http\Controllers;

use App\Models\CommuterPass;
use App\Models\User;
use App\Services\CommutingExpenses\CommutePatternService;
use Illuminate\Http\Request;

class CommuteRouteController extends Controller
{
    public function index(Request $request, CommutePatternService $patterns)
    {
        return $this->renderFor($request->user(), $request, $patterns, false);
    }

    public function showUser(User $user, Request $request, CommutePatternService $patterns)
    {
        $this->authorize('view', $user);

        return $this->renderFor($user, $request, $patterns, true);
    }

    private function renderFor(User $user, Request $request, CommutePatternService $patterns, bool $isAdminMode)
    {
        $passes = CommuterPass::query()
            ->where('user_id', $user->id)
            ->orderByDesc('date_from')
            ->orderByDesc('id')
            ->get();
        $visiblePatterns = $patterns->allForUser($user)
            ->filter(fn ($pattern) => $pattern->legs->contains(fn ($leg) => (int) $leg->cost > 0))
            ->values();

        return view('routes.index', [
            'targetUser' => $user,
            'isAdminMode' => $isAdminMode,
            'passes' => $passes,
            'patterns' => $visiblePatterns,
        ]);
    }
}
