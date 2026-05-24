<?php

namespace Tests\Feature;

use App\Enums\ExpenseReportStatus;
use App\Models\Expense;
use App\Models\ExpenseReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ExpenseApiController のテスト（store / update / destroy）
 * ※ batchSave は ExpenseApiControllerBatchSaveTest に分離済み
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [store — POST /api/expenses]
 *   1. ゲスト → 401
 *   2. 本人 → 201、DB にレコードが挿入される
 *   3. 他人のレポートへ追加しようとすると welcome へリダイレクト（AuthorizationException）
 *   4. SUBMITTED レポートへの追加は 423
 *   5. 対象月外の expense_date は 422
 *   6. trip_type が欠如している場合は 422
 *
 * [update — PUT /api/expenses/{expense}]
 *   7. ゲスト → 401
 *   8. 本人 → 200、DB が更新される
 *   9. 他人の expense を更新しようとすると welcome へリダイレクト（AuthorizationException）
 *  10. SUBMITTED レポートの expense 更新は 423
 *
 * [destroy — DELETE /api/expenses/{expense}]
 *  11. ゲスト → 401
 *  12. 本人 → 200、DB からレコードが削除される
 *  13. 他人の expense を削除しようとすると welcome へリダイレクト（AuthorizationException）
 *  14. SUBMITTED レポートの expense 削除は 423
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 注記: このアプリの例外ハンドラは AuthorizationException を JSON リクエストでも
 * 常に welcome へリダイレクト（302）する。
 * 423 は abort() 由来の HttpException なので JSON 形式で返る。
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ExpenseApiControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // ヘルパー
    // =========================================================================

    /**
     * ユーザーと、そのユーザーの今月の DRAFT レポートを生成する。
     *
     * @return array{0: User, 1: ExpenseReport}
     */
    private function makeUserWithReport(): array
    {
        $user = User::factory()->general()->create();

        $report = ExpenseReport::create([
            'user_id'              => $user->id,
            'employee_code'        => str_pad((string)$user->id, 4, '0', STR_PAD_LEFT),
            'employee_family_name' => 'テスト',
            'employee_first_name'  => null,
            'year'                 => now()->year,
            'month'                => now()->month,
            'status'               => ExpenseReportStatus::DRAFT,
            'total_amount'         => 0,
        ]);

        return [$user, $report];
    }

    /**
     * 指定レポートに紐づく Expense を1件生成する。
     */
    private function makeExpense(ExpenseReport $report, array $override = []): Expense
    {
        return Expense::create(array_merge([
            'expense_report_id' => $report->id,
            'expense_date'      => now()->startOfMonth()->toDateString(),
            'seq'               => 100,
            'station_from'      => '梅田',
            'station_to'        => '心斎橋',
            'cost'              => 240,
            'trip_type'         => 'round_trip',
            'category'          => 'regular',
        ], $override));
    }

    /** store リクエストの最小有効ペイロードを返す。 */
    private function storePayload(ExpenseReport $report, array $override = []): array
    {
        return array_merge([
            'expense_report_id' => $report->id,
            'expense_date'      => now()->startOfMonth()->toDateString(),
            'seq'               => 100,
            'station_from'      => '梅田',
            'station_to'        => '心斎橋',
            'cost'              => 240,
            'trip_type'         => 'round_trip',
            'category'          => 'regular',
        ], $override);
    }

    // =========================================================================
    // 1〜6. store（POST /api/expenses）
    // =========================================================================

    /**
     * シナリオ 1: 未認証ユーザーは 401
     */
    public function test_store_guest_returns_401(): void
    {
        $this->postJson(route('api.expenses.store'), [])->assertUnauthorized();
    }

    /**
     * シナリオ 2: 本人は expense を新規作成できる → 201、DB にレコードが挿入される
     *
     * - レスポンスの id を使って assertDatabaseHas でレコードの存在と値を確認
     */
    public function test_store_owner_can_create_expense(): void
    {
        [$user, $report] = $this->makeUserWithReport();

        $resp = $this->actingAs($user)
            ->postJson(route('api.expenses.store'), $this->storePayload($report, [
                'station_from' => '天王寺',
                'station_to'   => '難波',
                'cost'         => 180,
            ]));

        $resp->assertCreated();

        $this->assertDatabaseHas('expenses', [
            'expense_report_id' => $report->id,
            'station_from'      => '天王寺',
            'station_to'        => '難波',
            'cost'              => 180,
            'trip_type'         => 'round_trip',
        ]);
    }

    /**
     * シナリオ 3: 他人のレポートへ expense を追加しようとすると welcome へリダイレクト
     *
     * - ExpenseReportPolicy::update が失敗 → AuthorizationException
     * - カスタム例外ハンドラにより JSON リクエストでも 302 になる
     */
    public function test_store_other_users_report_redirects_to_welcome(): void
    {
        [, $report] = $this->makeUserWithReport();
        $attacker = User::factory()->general()->create();

        $this->actingAs($attacker)
            ->postJson(route('api.expenses.store'), $this->storePayload($report))
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 4: SUBMITTED レポートへの追加は 423
     *
     * - abortIfLocked が検出して 423 を返す
     */
    public function test_store_into_submitted_report_returns_423(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $report->update(['status' => ExpenseReportStatus::SUBMITTED]);

        $this->actingAs($user)
            ->postJson(route('api.expenses.store'), $this->storePayload($report))
            ->assertStatus(423);
    }

    /**
     * シナリオ 5: 対象月外の expense_date は 422
     *
     * - コントローラの月内チェック（abort_unless）で弾かれる
     */
    public function test_store_with_out_of_month_date_returns_422(): void
    {
        [$user, $report] = $this->makeUserWithReport();

        $nextMonth = now()->addMonth()->startOfMonth()->toDateString();

        $this->actingAs($user)
            ->postJson(route('api.expenses.store'), $this->storePayload($report, [
                'expense_date' => $nextMonth,
            ]))
            ->assertUnprocessable();
    }

    /**
     * シナリオ 6: trip_type が欠如している場合は 422（バリデーション）
     */
    public function test_store_without_trip_type_returns_422(): void
    {
        [$user, $report] = $this->makeUserWithReport();

        $payload = $this->storePayload($report);
        unset($payload['trip_type']);

        $this->actingAs($user)
            ->postJson(route('api.expenses.store'), $payload)
            ->assertUnprocessable();
    }

    // =========================================================================
    // 7〜10. update（PUT /api/expenses/{expense}）
    // =========================================================================

    /**
     * シナリオ 7: 未認証ユーザーは 401
     */
    public function test_update_guest_returns_401(): void
    {
        $this->putJson(route('api.expenses.update', 1), [])->assertUnauthorized();
    }

    /**
     * シナリオ 8: 本人は expense を更新できる → 200、DB が更新される
     *
     * - station_from / cost / trip_type / note / seq の各フィールドを確認
     */
    public function test_update_owner_can_update_expense(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $expense = $this->makeExpense($report);

        $this->actingAs($user)
            ->putJson(route('api.expenses.update', $expense), [
                'station_from' => '新大阪',
                'station_to'   => '三ノ宮',
                'cost'         => 680,
                'trip_type'    => 'one_way',
                'note'         => '出張',
                'seq'          => 512,
            ])
            ->assertOk();

        $this->assertDatabaseHas('expenses', [
            'id'           => $expense->id,
            'station_from' => '新大阪',
            'station_to'   => '三ノ宮',
            'cost'         => 680,
            'trip_type'    => 'one_way',
            'note'         => '出張',
            'seq'          => 512,
        ]);
    }

    /**
     * シナリオ 9: 他人の expense を更新しようとすると welcome へリダイレクト
     *
     * - ExpensePolicy::update → canManage が false → AuthorizationException
     * - カスタム例外ハンドラにより JSON リクエストでも 302 になる
     */
    public function test_update_other_users_expense_redirects_to_welcome(): void
    {
        [, $report] = $this->makeUserWithReport();
        $expense  = $this->makeExpense($report);
        $attacker = User::factory()->general()->create();

        $this->actingAs($attacker)
            ->putJson(route('api.expenses.update', $expense), [
                'cost'      => 9999,
                'trip_type' => 'one_way',
            ])
            ->assertRedirect(route('welcome'));

        // 値は変化していない
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'cost' => $expense->cost]);
    }

    /**
     * シナリオ 10: SUBMITTED レポートの expense を更新しようとすると 423
     */
    public function test_update_expense_in_submitted_report_returns_423(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $expense = $this->makeExpense($report);
        $report->update(['status' => ExpenseReportStatus::SUBMITTED]);

        $this->actingAs($user)
            ->putJson(route('api.expenses.update', $expense), [
                'cost'      => 9999,
                'trip_type' => 'one_way',
            ])
            ->assertStatus(423);

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'cost' => $expense->cost]);
    }

    // =========================================================================
    // 11〜14. destroy（DELETE /api/expenses/{expense}）
    // =========================================================================

    /**
     * シナリオ 11: 未認証ユーザーは 401
     */
    public function test_destroy_guest_returns_401(): void
    {
        $this->deleteJson(route('api.expenses.destroy', 1))->assertUnauthorized();
    }

    /**
     * シナリオ 12: 本人は expense を削除できる → 200、DB からレコードが消える
     */
    public function test_destroy_owner_can_delete_expense(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $expense = $this->makeExpense($report);

        $this->actingAs($user)
            ->deleteJson(route('api.expenses.destroy', $expense))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    /**
     * シナリオ 13: 他人の expense を削除しようとすると welcome へリダイレクト
     *
     * - ExpensePolicy::delete → canManage が false → AuthorizationException
     * - カスタム例外ハンドラにより JSON リクエストでも 302 になる
     * - DB のレコードは削除されていない
     */
    public function test_destroy_other_users_expense_redirects_to_welcome(): void
    {
        [, $report] = $this->makeUserWithReport();
        $expense  = $this->makeExpense($report);
        $attacker = User::factory()->general()->create();

        $this->actingAs($attacker)
            ->deleteJson(route('api.expenses.destroy', $expense))
            ->assertRedirect(route('welcome'));

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }

    /**
     * シナリオ 14: SUBMITTED レポートの expense を削除しようとすると 423
     *
     * - abortIfLocked が検出して 423 を返す
     * - DB のレコードは削除されていない
     */
    public function test_destroy_expense_in_submitted_report_returns_423(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $expense = $this->makeExpense($report);
        $report->update(['status' => ExpenseReportStatus::SUBMITTED]);

        $this->actingAs($user)
            ->deleteJson(route('api.expenses.destroy', $expense))
            ->assertStatus(423);

        $this->assertDatabaseHas('expenses', ['id' => $expense->id]);
    }
}
