<?php

namespace Tests\Feature;

use App\Enums\ExpenseCategory;
use App\Enums\ExpenseReportStatus;
use App\Enums\ExpenseTripType;
use App\Models\Department;
use App\Models\District;
use App\Models\Expense;
use App\Models\ExpenseReport;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ExpenseEditController のテスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [selfEdit — GET /expenses/edit]
 *   1. ゲスト → ログインページへリダイレクト
 *   2. 本人（当月レポートあり）→ 200、expenses.edit ビュー、hasReport=true
 *   3. 本人（レポートなし月）→ 200、expenses.edit ビュー、hasReport=false
 *
 * [adminEdit — GET /expenses/{user}/edit]
 *   4. ゲスト → ログインページへリダイレクト
 *   5. general ユーザーが他ユーザーの画面を見ようとすると welcome へリダイレクト
 *   6. admin がスコープ内のユーザーを閲覧できる → 200
 *   6-2. admin がスコープ外ユーザーの編集URLを踏むと経費一覧へリダイレクト
 *
 * [submit — PUT /expenses/reports/{report}/submit]
 *   7. 本人が DRAFT レポートを提出できる → status=SUBMITTED、submitted_at に値が入る
 *   8. 他人のレポートを提出しようとすると welcome へリダイレクト
 *   9. 提出後は本人の編集画面へリダイレクトされる
 *
 * [unsubmit — PATCH /expenses/{report}/unsubmit]
 *  10. admin が SUBMITTED を DRAFT に戻せる → status=DRAFT、submitted_at=null
 *  11. DRAFT 状態では unsubmit できない（バックへリダイレクト + エラーメッセージ）
 *  12. general ユーザーは unsubmit できない → welcome へリダイレクト
 *
 * [generateMonthly — POST /expenses/generate-monthly]
 *  13. admin → Artisan コマンドが呼ばれ back with toast
 *  14. general ユーザー → welcome へリダイレクト
 *
 * [cleanupEmpty — POST /expenses/cleanup-empty]
 *  15. admin → Artisan コマンドが呼ばれ back with toast
 *  16. general ユーザー → welcome へリダイレクト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 注記: AuthorizationException はカスタム例外ハンドラにより welcome へリダイレクト（302）
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ExpenseEditControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // ヘルパー
    // =========================================================================

    /**
     * ユーザーと指定年月の DRAFT レポートを生成する。
     *
     * @return array{0: User, 1: ExpenseReport}
     */
    private function makeUserWithReport(int $year = 0, int $month = 0): array
    {
        $user = User::factory()->general()->create();

        $y = $year ?: now()->year;
        $m = $month ?: now()->month;

        $report = ExpenseReport::create([
            'user_id'              => $user->id,
            'employee_code'        => str_pad((string)$user->id, 4, '0', STR_PAD_LEFT),
            'employee_family_name' => 'テスト',
            'employee_first_name'  => null,
            'year'                 => $y,
            'month'                => $m,
            'status'               => ExpenseReportStatus::DRAFT,
            'total_amount'         => 0,
        ]);

        return [$user, $report];
    }

    /**
     * admin ユーザーとスコープ（District + Department + UserManagementScope）を生成する。
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
     * admin のスコープ内に属するユーザーを生成する。
     */
    private function makeUserInScope(District $district, Department $department): User
    {
        return User::factory()->general()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);
    }

    // =========================================================================
    // 1〜3. selfEdit（GET /expenses/edit）
    // =========================================================================

    /**
     * シナリオ 1: ゲストはログインページへリダイレクト
     */
    public function test_self_edit_guest_is_redirected_to_login(): void
    {
        $this->get(route('expenses.edit'))
             ->assertRedirect(route('login'));
    }

    /**
     * シナリオ 2: 本人（当月レポートあり）→ 200、expenses.edit ビュー、hasReport=true
     *
     * - CalendarResolver / RouteDeclarationService は空データで動作する（モック不要）
     * - ビューに渡る hasReport が true であることを確認
     */
    public function test_self_edit_shows_expense_sheet_when_report_exists(): void
    {
        [$user, $report] = $this->makeUserWithReport();

        $this->actingAs($user)
             ->get(route('expenses.edit', ['year' => now()->year, 'month' => now()->month]))
             ->assertOk()
             ->assertViewIs('expenses.edit')
             ->assertViewHas('hasReport', true)
             ->assertViewHas('report', fn ($r) => $r->id === $report->id);
    }

    /**
     * シナリオ 3: 本人（レポートなし月）→ 200、hasReport=false で空シート表示
     *
     * - レポートが存在しない月でも 404 にならず画面が表示される
     */
    public function test_self_edit_shows_empty_sheet_when_no_report(): void
    {
        $user = User::factory()->general()->create();

        // レポートを作らずにアクセス
        $this->actingAs($user)
             ->get(route('expenses.edit', ['year' => now()->year, 'month' => now()->month]))
             ->assertOk()
             ->assertViewIs('expenses.edit')
             ->assertViewHas('hasReport', false);
    }

    // =========================================================================
    // 4〜6. adminEdit（GET /expenses/{user}/edit）
    // =========================================================================

    /**
     * シナリオ 4: ゲストはログインページへリダイレクト
     */
    public function test_admin_edit_guest_is_redirected_to_login(): void
    {
        $user = User::factory()->general()->create();

        $this->get(route('expenses.admin.edit', ['user' => $user->employee_code]))
             ->assertRedirect(route('login'));
    }

    /**
     * シナリオ 5: general ユーザーが他ユーザーの画面を見ようとすると welcome へリダイレクト
     *
     * - UserPolicy::view が失敗 → AuthorizationException → welcome redirect
     */
    public function test_admin_edit_general_user_cannot_view_others(): void
    {
        $viewer = User::factory()->general()->create();
        $target = User::factory()->general()->create();

        $this->actingAs($viewer)
             ->get(route('expenses.admin.edit', ['user' => $target->employee_code]))
             ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 6: admin がスコープ内のユーザーの編集画面を閲覧できる → 200
     *
     * - UserPolicy::isScopedAdminFor が CurrentScopeService 経由でスコープを確認
     * - selected_scope_id をセッションにセットすることで認証が通る
     * - Blade が $report->employee_code を参照するため、レポートを事前に作成する
     */
    public function test_admin_edit_admin_can_view_scoped_users_expenses(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $target = $this->makeUserInScope($district, $department);

        // Blade の $report->employee_code 参照を満たすためレポートを作成
        ExpenseReport::create([
            'user_id'              => $target->id,
            'employee_code'        => str_pad((string)$target->id, 4, '0', STR_PAD_LEFT),
            'employee_family_name' => 'テスト',
            'employee_first_name'  => null,
            'year'                 => now()->year,
            'month'                => now()->month,
            'status'               => ExpenseReportStatus::DRAFT,
            'total_amount'         => 0,
        ]);

        $this->actingAs($admin)
             ->withSession(['selected_scope_id' => $scope->id])
             ->get(route('expenses.admin.edit', ['user' => $target->employee_code]))
             ->assertOk()
             ->assertViewIs('expenses.edit');
    }

    /**
     * シナリオ 6-2: スコープ外ユーザーの古い編集URLは、年月を保ったまま経費一覧へ戻す
     */
    public function test_admin_edit_outside_scope_redirects_to_report(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $otherDistrict   = District::factory()->create();
        $otherDepartment = Department::factory()->create();
        $outsideUser = User::factory()->general()->create([
            'district_id'   => $otherDistrict->id,
            'department_id' => $otherDepartment->id,
        ]);

        $this->actingAs($admin)
             ->withSession(['selected_scope_id' => $scope->id])
             ->get(route('expenses.admin.edit', [
                 'user' => $outsideUser->employee_code,
                 'year' => 2026,
                 'month' => 5,
             ]))
             ->assertRedirect(route('expenses.admin.report', ['year' => 2026, 'month' => 5]));
    }

    // =========================================================================
    // 7〜9. submit（PUT /expenses/reports/{report}/submit）
    // =========================================================================

    /**
     * シナリオ 7: 本人が DRAFT レポートを提出すると SUBMITTED になり submitted_at が記録される
     *
     * - status が SUBMITTED に変わる
     * - submitted_at に NULL 以外の値が入る
     */
    public function test_submit_changes_status_to_submitted(): void
    {
        [$user, $report] = $this->makeUserWithReport();

        $this->actingAs($user)
             ->put(route('expenses.submit', $report))
             ->assertRedirect();

        $report->refresh();
        $this->assertSame(ExpenseReportStatus::SUBMITTED, $report->status);
        $this->assertNotNull($report->submitted_at);
    }

    /**
     * シナリオ 8: 他人のレポートを提出しようとすると welcome へリダイレクト
     *
     * - ExpenseReportPolicy::submit が失敗 → AuthorizationException
     * - DB の status は変化しない
     */
    public function test_submit_other_users_report_redirects_to_welcome(): void
    {
        [$owner, $report] = $this->makeUserWithReport();
        $attacker = User::factory()->general()->create();

        $this->actingAs($attacker)
             ->put(route('expenses.submit', $report))
             ->assertRedirect(route('welcome'));

        $this->assertSame(ExpenseReportStatus::DRAFT, $report->fresh()->status);
    }

    /**
     * シナリオ 9: 提出後は本人の編集画面（当該年月）へリダイレクトされる
     *
     * - リダイレクト先に year / month が含まれることを確認
     */
    public function test_submit_redirects_to_expenses_edit_with_year_month(): void
    {
        [$user, $report] = $this->makeUserWithReport();

        $this->actingAs($user)
             ->put(route('expenses.submit', $report))
             ->assertRedirect(
                 route('expenses.edit', ['year' => $report->year, 'month' => $report->month])
             );
    }

    public function test_submit_requires_note_for_positive_cost_on_non_working_day(): void
    {
        [$user, $report] = $this->makeUserWithReport(2026, 5);

        Expense::create([
            'expense_report_id' => $report->id,
            'expense_date' => '2026-05-01',
            'seq' => 100,
            'station_from' => 'Home',
            'station_to' => 'School',
            'note' => null,
            'cost' => 240,
            'trip_type' => ExpenseTripType::ROUND_TRIP->value,
            'category' => ExpenseCategory::REGULAR->value,
        ]);

        $this->actingAs($user)
            ->put(route('expenses.submit', $report))
            ->assertRedirect()
            ->assertSessionHas('toast_errors', function ($errors) {
                return in_array(
                    'Please write a reason in Note for travel expenses on non-working days. First invalid row: 2026-05-01.',
                    $errors,
                    true
                );
            });

        $report->refresh();
        $this->assertSame(ExpenseReportStatus::DRAFT, $report->status);
        $this->assertNull($report->submitted_at);
    }

    // =========================================================================
    // 10〜12. unsubmit（PATCH /expenses/{report}/unsubmit）
    // =========================================================================

    /**
     * シナリオ 10: admin が SUBMITTED レポートを DRAFT に差し戻せる
     *
     * - status が DRAFT に戻る
     * - submitted_at が null になる
     */
    public function test_unsubmit_admin_can_return_submitted_report_to_draft(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $target = $this->makeUserInScope($district, $department);

        $report = ExpenseReport::create([
            'user_id'              => $target->id,
            'employee_code'        => str_pad((string)$target->id, 4, '0', STR_PAD_LEFT),
            'employee_family_name' => 'テスト',
            'employee_first_name'  => null,
            'year'                 => now()->year,
            'month'                => now()->month,
            'status'               => ExpenseReportStatus::SUBMITTED,
            'submitted_at'         => now(),
            'total_amount'         => 0,
        ]);

        $this->actingAs($admin)
             ->withSession(['selected_scope_id' => $scope->id])
             ->patch(route('expenses.unsubmit', $report))
             ->assertRedirect();

        $report->refresh();
        $this->assertSame(ExpenseReportStatus::DRAFT, $report->status);
        $this->assertNull($report->submitted_at);
    }

    /**
     * シナリオ 11: DRAFT 状態のレポートは unsubmit できない → back with toast_errors
     *
     * - コントローラの status チェックに引っかかり、back() にエラーが返る
     */
    public function test_unsubmit_draft_report_returns_error(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $target = $this->makeUserInScope($district, $department);

        $report = ExpenseReport::create([
            'user_id'              => $target->id,
            'employee_code'        => str_pad((string)$target->id, 4, '0', STR_PAD_LEFT),
            'employee_family_name' => 'テスト',
            'employee_first_name'  => null,
            'year'                 => now()->year,
            'month'                => now()->month,
            'status'               => ExpenseReportStatus::DRAFT,
            'total_amount'         => 0,
        ]);

        $this->actingAs($admin)
             ->withSession(['selected_scope_id' => $scope->id])
             ->patch(route('expenses.unsubmit', $report))
             ->assertRedirect();

        // ステータスは変わっていない
        $this->assertSame(ExpenseReportStatus::DRAFT, $report->fresh()->status);
    }

    /**
     * シナリオ 12: general ユーザーは unsubmit できない → welcome へリダイレクト
     *
     * - ExpenseReportPolicy::unsubmit は isScopedAdminFor のみ許可
     */
    public function test_unsubmit_general_user_is_redirected_to_welcome(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $report->update(['status' => ExpenseReportStatus::SUBMITTED, 'submitted_at' => now()]);

        $this->actingAs($user)
             ->patch(route('expenses.unsubmit', $report))
             ->assertRedirect(route('welcome'));

        $this->assertSame(ExpenseReportStatus::SUBMITTED, $report->fresh()->status);
    }

    // =========================================================================
    // 13〜14. generateMonthly（POST /expenses/generate-monthly）
    // =========================================================================

    /**
     * シナリオ 13: admin は generateMonthly を実行でき、back with toast が返る
     *
     * - welcome へリダイレクトされず、コントローラが back() を返すことを確認
     * - Artisan::fake() は Laravel 12 のカスタム Kernel では未対応のため使用しない
     */
    public function test_generate_monthly_admin_can_execute(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $this->actingAs($admin)
             ->withSession(['selected_scope_id' => $scope->id])
             ->post(route('expenses.generateMonthly'), [
                 'year'  => now()->year,
                 'month' => now()->month,
             ])
             ->assertRedirect();
    }

    /**
     * シナリオ 14: general ユーザーは generateMonthly を実行できない → welcome へリダイレクト
     *
     * - ExpenseReportPolicy::manage（admin のみ許可）で弾かれる
     */
    public function test_generate_monthly_general_user_is_redirected_to_welcome(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)
             ->post(route('expenses.generateMonthly'), [
                 'year'  => now()->year,
                 'month' => now()->month,
             ])
             ->assertRedirect(route('welcome'));
    }

    // =========================================================================
    // 15〜16. cleanupEmpty（POST /expenses/cleanup-empty）
    // =========================================================================

    /**
     * シナリオ 15: admin は cleanupEmpty を実行でき、back with toast が返る
     */
    public function test_cleanup_empty_admin_can_execute(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $this->actingAs($admin)
             ->withSession(['selected_scope_id' => $scope->id])
             ->post(route('expenses.cleanupEmpty'), [
                 'year'  => now()->year,
                 'month' => now()->month,
             ])
             ->assertRedirect();
    }

    /**
     * シナリオ 16: general ユーザーは cleanupEmpty を実行できない → welcome へリダイレクト
     */
    public function test_cleanup_empty_general_user_is_redirected_to_welcome(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)
             ->post(route('expenses.cleanupEmpty'))
             ->assertRedirect(route('welcome'));
    }
}
