<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CurrentScopeService;
use Illuminate\Http\Request;

class UserSearchController extends Controller
{
    public function __construct(private CurrentScopeService $scopeService) {}

    /**
     * ユーザー検索 API（AJAX 用）
     * GET /users/search — ids クエリで ID 複数指定するとチップ復元用データを返す。q クエリで名前・社員コード・メールを部分一致検索
     */
    public function index(Request $r)
    {
        $this->authorize('viewAny', User::class);

        // ▼ old('recipients') でIDしか復元できない場合に、Vueのチップ表示用にユーザー情報を取得する
        $ids = trim((string) $r->query('ids', ''));
        if ($ids !== '') {
            $idArr = collect(explode(',', $ids))
                ->map(fn($v) => (int) trim($v))
                ->filter(fn($v) => $v > 0)
                ->unique()
                ->values()
                ->all();

            $users = $this->scopeService->targetUserQuery()
                ->whereIn('id', $idArr)
                ->orderBy('family_name')->orderBy('first_name')
                ->get(['id', 'first_name', 'family_name', 'employee_code', 'email']);

            return response()->json($users);
        }
        // ▲
        
        $q = trim((string) $r->query('q', ''));
        $limit = (int) min(max((int) $r->query('limit', 20), 1), 50);

        $users = $this->scopeService->targetUserQuery()
            ->when($q !== '', function ($qb) use ($q) {
                $qb->where(
                    fn($w) =>
                    $w->whereLikeInsensitive('first_name', $q)
                        ->orWhereLikeInsensitive('family_name', $q)
                        ->orWhereLikeInsensitive('employee_code', $q)
                        ->orWhereLikeInsensitive('email', $q)
                );
            })
            ->orderBy('family_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get(['id', 'first_name', 'family_name', 'employee_code', 'email']);

        return response()->json($users);
    }
}
