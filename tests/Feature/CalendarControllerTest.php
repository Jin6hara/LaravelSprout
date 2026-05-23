<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CalendarController のテスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [ゲスト]
 *   1. 未認証アクセスはログインページへリダイレクト（index / forecast / leave）
 *
 * [権限: general ユーザー]
 *   2. /calendar（自分のカレンダー）は表示できる → 200
 *   3. /calendar/{他人}（他ユーザー指定）は role ミドルウェアで 403
 *   4. /forecast は role:admin|super_admin ミドルウェアで 403
 *   5. /leave（LeaveCalendar）は role:admin|super_admin ミドルウェアで 403
 *
 * [権限: admin ユーザー（正常系）]
 *   6. /calendar（ユーザー未指定）は表示できる → 200
 *   7. /calendar/{スコープ内ユーザー} は表示できる → 200
 *   8. /forecast は表示できる → 200
 *   9. /leave は表示できる → 200
 *
 * [スコープ分離]
 *  10. admin が /calendar/{スコープ外ユーザー} にアクセスすると welcome へリダイレクト
 *      （UserPolicy::view → isScopedAdminFor が false → AuthorizationException → 302）
 *  11. スコープが切り替わった後に古いスコープのユーザーURLを踏むと /calendar へリダイレクト
 *      （district_id 不一致でリセット）
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * テストのセットアップ方針
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - RefreshDatabase でテストごとに DB をリセット
 * - TestCase::setUp() で RoleSeeder が実行済み（admin/super_admin/general ロール作成）
 * - makeAdmin() で admin ユーザー + UserManagementScope を生成
 * - makeUserInScope() でスコープ内の general ユーザーを生成
 *   （UserManagementScope の district_id / department_id と同じ district/department）
 * - CurrentScopeService は session('selected_scope_id') でスコープを解決するため、
 *   withSession(['selected_scope_id' => $scope->id]) を各リクエストに付与する
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 重要な仕様メモ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - /forecast・/leave は route ミドルウェア 'role:admin|super_admin' で保護されており、
 *   general ユーザーは middleware が返す 403 でブロックされる（Controller まで到達しない）
 *   ※ Handler.php の AuthorizationException → redirect('welcome') とは別経路
 *
 * - /calendar/{user} は route ミドルウェア 'role:admin|super_admin' で保護されており、
 *   general ユーザーは middleware が返す 403 でブロックされる
 *
 * - /calendar（ユーザー未指定）は 'auth' のみ。general ユーザーも自分自身のカレンダーを閲覧可能。
 *   コントローラーが $canViewAnyUser = false の場合は $viewUser = $viewer（自分）に固定する。
 *
 * - admin が /calendar/{スコープ外ユーザー} にアクセスすると
 *   UserPolicy::view → isScopedAdminFor が false → AuthorizationException
 *   → Handler.php が redirect()->route('welcome') を返す（302）
 */
class CalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    // =========================================================================
    // ヘルパー
    // =========================================================================

    /**
     * admin ユーザー + District + Department + UserManagementScope を一括生成する。
     *
     * @return array{0: User, 1: UserManagementScope, 2: District, 3: Department}
     */
    private function makeAdmin(): array
    {
        $district   = District::factory()->create();
        $department = Department::factory()->create();

        $admin = User::factory()->admin()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        $scope = UserManagementScope::factory()->create([
            'user_id'       => $admin->id,
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        return [$admin, $scope, $district, $department];
    }

    /**
     * admin のスコープ内（同じ district / department）に general ユーザーを生成する。
     * UserPolicy::view → isScopedAdminFor が true になる条件を満たす。
     */
    private function makeUserInScope(District $district, Department $department): User
    {
        return User::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);
    }

    // =========================================================================
    // 1. ゲスト
    // =========================================================================

    /**
     * シナリオ 1: 未認証ユーザーは各カレンダー画面にアクセスするとログインへリダイレクト
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('calendar.index'))->assertRedirect(route('login'));
        $this->get(route('calendar.forecast'))->assertRedirect(route('login'));
        $this->get(route('calendar.leaves'))->assertRedirect(route('login'));
    }

    // =========================================================================
    // 2〜5. general ユーザー
    // =========================================================================

    /**
     * シナリオ 2: general ユーザーは自分の /calendar を表示できる → 200
     *
     * - /calendar は 'auth' のみで保護。general でも自分のカレンダーは閲覧可能。
     * - UserPolicy::view($viewer, $viewer) → $currentUser->is($targetUser) = true
     */
    public function test_general_user_can_view_own_calendar(): void
    {
        $general = User::factory()->create();

        $this->actingAs($general)
            ->get(route('calendar.index'))
            ->assertOk();
    }

    /**
     * シナリオ 3: general ユーザーは /calendar/{他人} にアクセスすると 403
     *
     * - /calendar/{user} は route ミドルウェア 'role:admin|super_admin' で保護。
     * - general ユーザーは middleware 段階でブロックされ Controller まで到達しない。
     */
    public function test_general_user_cannot_view_other_user_calendar(): void
    {
        $general     = User::factory()->create();
        $otherUser   = User::factory()->create();

        $this->actingAs($general)
            ->get(route('calendar.index.user', $otherUser))
            ->assertForbidden();
    }

    /**
     * シナリオ 4: general ユーザーは /forecast にアクセスすると 403
     *
     * - /forecast は route ミドルウェア 'role:admin|super_admin' で保護。
     * - general ユーザーは middleware 段階でブロックされ Controller まで到達しない。
     */
    public function test_general_user_cannot_view_forecast(): void
    {
        $general = User::factory()->create();

        $this->actingAs($general)
            ->get(route('calendar.forecast'))
            ->assertForbidden();
    }

    /**
     * シナリオ 5: general ユーザーは /leave（LeaveCalendar）にアクセスすると 403
     *
     * - /leave は route ミドルウェア 'role:admin|super_admin' で保護。
     * - general ユーザーは middleware 段階でブロックされ Controller まで到達しない。
     */
    public function test_general_user_cannot_view_leave_calendar(): void
    {
        $general = User::factory()->create();

        $this->actingAs($general)
            ->get(route('calendar.leaves'))
            ->assertForbidden();
    }

    // =========================================================================
    // 6〜9. admin ユーザー（正常系）
    // =========================================================================

    /**
     * シナリオ 6: admin はユーザー未指定の /calendar を表示できる → 200
     */
    public function test_admin_can_view_calendar_index(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('calendar.index'))
            ->assertOk();
    }

    /**
     * シナリオ 7: admin はスコープ内ユーザーの /calendar/{user} を表示できる → 200
     *
     * - UserPolicy::view → isScopedAdminFor が true（同じ district の targetUserIds に含まれる）
     */
    public function test_admin_can_view_calendar_for_user_in_scope(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $targetUser = $this->makeUserInScope($district, $department);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('calendar.index.user', $targetUser))
            ->assertOk();
    }

    /**
     * シナリオ 8: admin は /forecast を表示できる → 200
     */
    public function test_admin_can_view_forecast(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('calendar.forecast'))
            ->assertOk();
    }

    /**
     * シナリオ 9: admin は /leave（LeaveCalendar）を表示できる → 200
     */
    public function test_admin_can_view_leave_calendar(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('calendar.leaves'))
            ->assertOk();
    }

    // =========================================================================
    // 10〜11. スコープ分離
    // =========================================================================

    /**
     * シナリオ 10: admin がスコープ外ユーザーの /calendar/{user} にアクセスすると welcome へリダイレクト
     *
     * - UserPolicy::view → isScopedAdminFor が false（別 district のユーザーは targetUserIds に含まれない）
     * - AuthorizationException → Handler.php が redirect()->route('welcome') を返す（302）
     */
    public function test_admin_cannot_view_calendar_for_user_outside_scope(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $otherDistrict   = District::factory()->create();
        $otherDepartment = Department::factory()->create();
        $outsideUser     = User::factory()->create([
            'district_id'   => $otherDistrict->id,
            'department_id' => $otherDepartment->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('calendar.index.user', $outsideUser))
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 11: スコープ切り替え後に古いスコープのユーザーURLを踏むと /calendar へリダイレクト
     *
     * - コントローラーが $viewUser->district_id !== currentDistrictId() を検出してリセット
     * - admin が別スコープ（別 district）に切り替えた状態で旧 URL を踏む想定
     */
    public function test_admin_is_redirected_to_index_when_scope_does_not_match_user(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        // 旧スコープのユーザー（admin と同じ district に所属）
        $oldUser = $this->makeUserInScope($district, $department);

        // 新スコープ（別 district）に切り替える
        $newDistrict   = District::factory()->create();
        $newDepartment = Department::factory()->create();
        $newScope      = UserManagementScope::factory()->create([
            'user_id'       => $admin->id,
            'district_id'   => $newDistrict->id,
            'department_id' => $newDepartment->id,
        ]);

        // 新スコープのセッションで旧ユーザーの URL を踏む
        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $newScope->id])
            ->get(route('calendar.index.user', $oldUser))
            ->assertRedirect(route('calendar.index'));
    }
}
