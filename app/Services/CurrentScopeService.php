<?php

namespace App\Services;

use App\Models\School;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Database\Eloquent\Builder;

class CurrentScopeService
{
    /** リクエスト内でキャッシュ済みかどうか */
    private ?UserManagementScope $resolvedScope = null;
    private bool $scopeLoaded = false;

    /**
     * ログインユーザーの現在の管理スコープを返す。
     *
     * - admin / super_admin: session('selected_scope_id') を参照し、
     *   そのユーザーに紐づくスコープであるか検証する。
     *   未設定の場合は最初のスコープを初期値として使う。
     * - general: null を返す（users.district_id / department_id を直接使う）
     */
    public function currentScope(): ?UserManagementScope
    {
        if ($this->scopeLoaded) {
            return $this->resolvedScope;
        }

        $this->scopeLoaded = true;

        $user = auth()->user();
        if (!$user || !$user->isAdmin()) {
            return null;
        }

        $scopeId = session('selected_scope_id');

        $query = UserManagementScope::where('user_id', $user->id)
            ->with(['district', 'department']);

        $this->resolvedScope = $scopeId
            ? $query->where('id', $scopeId)->first()
            : $query->first(); // 未選択時は最初のスコープを初期値に

        return $this->resolvedScope;
    }

    /**
     * 現在のリクエストで使用する district_id を返す。
     *
     * - admin / super_admin: 選択中スコープの district_id
     * - general: users.district_id
     */
    public function currentDistrictId(): ?int
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return $user->isAdmin()
            ? $this->currentScope()?->district_id
            : $user->district_id;
    }

    /**
     * 現在のリクエストで使用する department_id を返す。
     *
     * - admin / super_admin: 選択中スコープの department_id
     * - general: users.department_id
     */
    public function currentDepartmentId(): ?int
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        return $user->isAdmin()
            ? $this->currentScope()?->department_id
            : $user->department_id;
    }

    /**
     * 対象ユーザーのクエリビルダーを返す。
     *
     * SQL イメージ:
     *   WHERE (district_id = ? AND department_id = ?)
     *
     * - admin / super_admin: 現在の district_id AND department_id に一致する User
     *   スコープ未設定（どちらかが null）の場合は自分自身のみ
     * - general: 自分自身のみ
     */
    public function targetUserQuery(): Builder
    {
        $user   = auth()->user();
        $selfId = $user?->id;

        if ($user && $user->isAdmin()) {
            $did = $this->currentDistrictId();
            $dep = $this->currentDepartmentId();

            if ($did !== null && $dep !== null) {
                return User::where('district_id', $did)
                           ->where('department_id', $dep);
            }
        }

        // general user またはスコープ未設定 → 自分自身のみ
        return User::where('id', $selfId);
    }

    /**
     * 対象ユーザーの ID 配列を返す（targetUserQuery のラッパー）。
     */
    public function targetUserIds(): array
    {
        return $this->targetUserQuery()->pluck('id')->all();
    }

    /**
     * 対象 School のクエリビルダーを返す。
     *
     * district_id が null の場合は空結果を返す。
     * スコープ未設定時に全 School が見えるのを防ぐ。
     */
    public function schoolQuery(): Builder
    {
        $did = $this->currentDistrictId();

        if ($did === null) {
            // 意図的に空結果を返す（全件取得を防ぐ）
            return School::whereRaw('1 = 0');
        }

        return School::where('district_id', $did);
    }
}
