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

        return redirect()->route('user.master_list')->with('toast', '登録成功');
    }

    public function masterList(Request $request): View
    {
        $this->authorize('viewTeacherAny', User::class);

        $todayDate = today();
        $today = $todayDate->toDateString();
        $viewer = $request->user();
        $search = trim((string) $request->query('search', ''));
        $statuses = collect((array) $request->query('statuses', ['active']))
            ->intersect(['active', 'terminated'])
            ->values();

        if ($statuses->isEmpty()) {
            $statuses = collect(['active']);
        }

        $districtId = $this->scopeService->currentDistrictId();
        $departmentId = $this->scopeService->currentDepartmentId();

        $query = User::query()
            ->when(
                $districtId !== null && $departmentId !== null,
                fn (Builder $q) => $q
                    ->where('district_id', $districtId)
                    ->where('department_id', $departmentId),
                fn (Builder $q) => $q->whereKey($viewer->id)
            )
            ->when(filled($search), function (Builder $q) use ($search) {
                $q->where(function (Builder $w) use ($search) {
                    $w->whereLikeInsensitive('employee_code', $search)
                        ->orWhereLikeInsensitive('first_name', $search)
                        ->orWhereLikeInsensitive('family_name', $search)
                        ->orWhereLikeInsensitive('phone_number', $search)
                        ->orWhereHas('employmentTerms', fn (Builder $term) => $term->whereLikeInsensitive('type_code', $search))
                        ->orWhereHas('restPatternAssignments.pattern', fn (Builder $pattern) => $pattern
                            ->whereLikeInsensitive('name', $search)
                            ->orWhereLikeInsensitive('code', $search));
                });
            })
            ->where(function (Builder $q) use ($statuses, $today, $todayDate) {
                if ($statuses->contains('active')) {
                    $q->whereHas('employmentTerms', fn (Builder $term) => $term->currentAt($today));
                }

                if ($statuses->contains('terminated')) {
                    $method = $statuses->contains('active') ? 'orWhere' : 'where';
                    $q->{$method}(fn (Builder $terminated) => $terminated->terminated($todayDate));
                }
            });

        $rows = $query
            ->with([
                'district',
                'department',
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
            ->map(function (User $user) use ($today, $viewer) {
                $term = $user->employmentTerms->first(
                    fn ($row) => $row->start_date?->toDateString() <= $today
                        && (is_null($row->end_date) || $row->end_date->toDateString() >= $today)
                ) ?? $user->employmentTerms->first();

                $restAssignment = $user->restPatternAssignments->first(
                    fn ($row) => $row->start_date?->toDateString() <= $today
                        && (is_null($row->end_date) || $row->end_date->toDateString() >= $today)
                ) ?? $user->restPatternAssignments->first();

                return [
                    'detail_url' => $viewer->isAdmin()
                        ? ($viewer->is($user) ? route('user.profile') : route('admin.user.profile', $user))
                        : route('calendar.index.user', $user),
                    'detail_label' => $viewer->isAdmin() ? 'Details' : 'Schedule',
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
                    'district_name' => $user->district?->name,
                    'department_name' => $user->department?->name,
                    'created_at' => $user->created_at?->format('Y-m-d H:i'),
                    'updated_at' => $user->updated_at?->format('Y-m-d H:i'),
                ];
            })
            ->values();

        $summary = [
            'count' => $rows->count(),
        ];

        return view('user.master_list', compact('rows', 'summary', 'search', 'statuses'));
    }

}
