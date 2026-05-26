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

    /**
     * ユーザー登録フォーム表示（現在のスコープ情報を表示）
     * GET /admin/register
     */
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

    public function masterList(): View
    {
        $this->authorize('viewAny', User::class);

        $today = today()->toDateString();

        $rows = $this->scopeService->targetUserQuery()
            ->with([
                'employmentTerms' => fn ($q) => $q
                    ->orderByRaw('CASE WHEN start_date <= ? AND (end_date IS NULL OR end_date >= ?) THEN 0 ELSE 1 END', [$today, $today])
                    ->orderByDesc('start_date'),
                'restPatternAssignments' => fn ($q) => $q
                    ->with('pattern')
                    ->orderByRaw('CASE WHEN start_date <= ? AND (end_date IS NULL OR end_date >= ?) THEN 0 ELSE 1 END', [$today, $today])
                    ->orderByDesc('start_date'),
            ])
            ->orderBy('employee_code')
            ->get()
            ->map(function (User $user) use ($today) {
                $term = $user->employmentTerms->first(
                    fn ($row) => $row->start_date?->toDateString() <= $today
                        && (is_null($row->end_date) || $row->end_date->toDateString() >= $today)
                ) ?? $user->employmentTerms->first();

                $restAssignment = $user->restPatternAssignments->first(
                    fn ($row) => $row->start_date?->toDateString() <= $today
                        && (is_null($row->end_date) || $row->end_date->toDateString() >= $today)
                ) ?? $user->restPatternAssignments->first();

                return [
                    'profile_url' => route('admin.user.profile', $user),
                    'employee_code' => $user->employee_code,
                    'family_name' => $user->family_name,
                    'first_name' => $user->first_name,
                    'nick_name' => $user->middle_name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'address' => $user->address,
                    'user_note' => $user->note,
                    'employment_start_date' => $term?->start_date?->format('Y-m-d'),
                    'employment_end_date' => $term?->end_date?->format('Y-m-d'),
                    'employment_type_code' => $term?->type_code,
                    'employment_note' => $term?->note,
                    'rest_pattern_name' => $restAssignment?->pattern?->name,
                ];
            })
            ->values();

        $summary = [
            'count' => $rows->count(),
        ];

        return view('user.master_list', compact('rows', 'summary'));
    }

    /**
     * ダッシュボード・検索共通のユーザークエリ構築
     * 検索ワード・対象フィールド・ソート順を受け取り [users, word, fields] を返す
     */
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
