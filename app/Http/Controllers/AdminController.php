<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\Admin\UserRegistrationService;
use App\Services\CurrentScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __construct(
        private CurrentScopeService $scopeService,
        private UserRegistrationService $registrationService
    ) {}

    /**
     * ダッシュボード（初期表示：全件→検索UIも表示）
     */
    public function dashboard(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        [$users, $word, $fields] = $this->buildUserQuery($request);

        return view('admin.dashboard', compact('users', 'word', 'fields'));
    }

    public function showForm(): View
    {
        $this->authorize('create', User::class);

        // 現在のスコープ（地区・部署）を登録フォームで確認表示するために渡す
        // currentScope() が内部で district/department を with() 済みのため load() 不要
        $scope = $this->scopeService->currentScope();
        return view('auth.register', compact('scope'));
    }

    /**
     * 登録処理（管理者用）
     * Handle user registration by validating input and creating user and employment terms records.
     */
    public function register(UserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $this->registrationService->register(
            $request->validated(),
            $this->scopeService->currentDistrictId(),
            $this->scopeService->currentDepartmentId()
        );

        return redirect()->route('admin.dashboard')->with('toast', '登録成功');
    }

    /**
     * 検索（GET /admin/search）
     * 実質は dashboard と100％同じロジック。URL表示を分けるため、ルートだけ分離。
     */
    public function search(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        [$users, $word, $fields] = $this->buildUserQuery($request);

        return view('admin.dashboard', compact('users', 'word', 'fields'));
    }

    private function buildUserQuery(Request $request): array
    {
        $allowedFields = ['employee_code', 'name', 'phone_number'];

        $word   = trim((string) $request->query('search_word', ''));
        $fields = collect((array) $request->query('fields', $allowedFields))
            ->intersect($allowedFields)
            ->take(3);

        if ($fields->isEmpty()) {
            $fields = collect($allowedFields);
        }

        $allowedSorts = ['updated_at', 'employee_code', 'name'];
        $allowedDirs  = ['asc', 'desc'];

        $sort = $request->query('sort', 'updated_at');
        $dir  = strtolower($request->query('dir', 'desc'));

        if (!in_array($sort, $allowedSorts, true)) $sort = 'updated_at';
        if (!in_array($dir,  $allowedDirs,  true)) $dir  = 'desc';

        $users = $this->scopeService->targetUserQuery()
            ->when(filled($word), function (Builder $q) use ($word, $fields) {
                $q->where(function (Builder $w) use ($word, $fields) {
                    $fields->values()->each(function ($field, $i) use ($w, $word) {
                        $method = $i === 0 ? 'whereLikeInsensitive' : 'orWhereLikeInsensitive';
                        $w->{$method}($field, $word);
                    });
                });
            })
            ->orderBy($sort, $dir)
            ->with(['district', 'department', 'latestEmploymentTerm'])
            ->paginate(10)
            ->withQueryString();

        return [$users, $word, $fields];
    }

}
