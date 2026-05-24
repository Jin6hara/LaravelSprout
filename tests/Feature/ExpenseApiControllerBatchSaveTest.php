<?php

namespace Tests\Feature;

use App\Enums\ExpenseReportStatus;
use App\Models\Expense;
use App\Models\ExpenseReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ExpenseApiController::batchSave() のテスト
 * ルート: PUT /api/expenses/batch
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [ゲスト]
 *   1. 未認証アクセスはログインページへリダイレクト
 *
 * [正常系 - 本人操作]
 *   2. updates のみ（新規行なし）: 200 が返り DB が更新される
 *   3. creates のみ（既存行なし）: 200 が返り DB に行が挿入される
 *   4. updates + creates 混在: 200 が返り両方とも DB に反映される
 *   5. updates / creates が両方空配列: 200 が返り DB は変化しない
 *
 * [セキュリティ - 他人のレポート]
 *   6. 他人のレポートへのアクセスは 403
 *   7. updates[*].id に別レポートの expense ID を混入すると 403
 *
 * [ロック]
 *   8. SUBMITTED 状態のレポートへのアクセスは 423
 *   9. APPROVED 状態のレポートへのアクセスは 423
 *
 * [バリデーション]
 *  10. report_id が存在しないレコードを指定すると 422
 *  11. creates[*].expense_date が対象月の範囲外なら 422
 *  12. creates[*].trip_type が欠如している行は 422
 *  13. updates[*].id が未指定（または不正な型）なら 422
 *
 * [DB 反映の詳細確認]
 *  14. updates 後の各フィールド値が正しく保存される
 *  15. creates 後のレコードが expense_report_id と紐づいて保存される
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 注記: batchSave は ExpenseReportPolicy::update() を使用。
 * 本人（report.user_id 一致）またはスコープ内 admin のみ操作可能。
 * ここでは「本人」ケースをメインにテストする。
 * ─────────────────────────────────────────────────────────────────────────────
 */
class ExpenseApiControllerBatchSaveTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // ヘルパー
    // =========================================================================

    /**
     * ユーザーと、そのユーザーの今月の DRAFT レポートを生成する。
     *
     * Note: ExpenseReportFactory は employee_first_middle_name という実在しないカラムを
     * 含むため、ここでは factory を使わず直接 create() する。
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
     * 指定レポートに紐づく Expense を1件生成する。
     *
     * Note: ExpenseFactory は expenses テーブルに存在しない user_id カラムを定義しているため、
     * ここでは factory を使わず直接 create() する。
     */
    private function makeExpense(ExpenseReport $report, array $override = []): Expense
    {
        $date = now()->setYear($report->year)->setMonth($report->month)->startOfMonth()->toDateString();

        return Expense::create(array_merge([
            'expense_report_id' => $report->id,
            'expense_date'      => $date,
            'seq'               => 100,
            'station_from'      => '梅田',
            'station_to'        => '心斎橋',
            'cost'              => 240,
            'trip_type'         => 'round_trip',
            'category'          => 'regular',
        ], $override));
    }

    /**
     * batchSave リクエストを送信する。
     *
     * @param  User           $user
     * @param  ExpenseReport  $report
     * @param  array          $updates  更新行の配列
     * @param  array          $creates  新規作成行の配列
     */
    private function putBatch(User $user, ExpenseReport $report, array $updates = [], array $creates = [])
    {
        return $this->actingAs($user)->putJson(route('api.expenses.batch'), [
            'report_id' => $report->id,
            'updates'   => $updates,
            'creates'   => $creates,
        ]);
    }

    // =========================================================================
    // 1. ゲスト
    // =========================================================================

    /**
     * シナリオ 1: 未認証ユーザーは PUT /api/expenses/batch にアクセスするとリダイレクト
     */
    public function test_guest_is_redirected(): void
    {
        $this->putJson(route('api.expenses.batch'), [
            'report_id' => 1,
            'updates'   => [],
            'creates'   => [],
        ])->assertUnauthorized();
    }

    // =========================================================================
    // 2〜5. 正常系 - 本人操作
    // =========================================================================

    /**
     * シナリオ 2: updates のみ（creates は空）を送信すると 200 が返り既存行が更新される
     *
     * - 変更前の station_from / cost / trip_type を変更後の値で上書き
     * - レスポンスの updated 配列に1件含まれる
     * - DB にも新しい値が反映されている
     */
    public function test_updates_only_returns_200_and_persists_changes(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $expense = $this->makeExpense($report);

        $this->putBatch($user, $report, updates: [
            [
                'id'           => $expense->id,
                'station_from' => '天王寺',
                'station_to'   => '難波',
                'cost'         => 500,
                'trip_type'    => 'one_way',
                'note'         => 'テスト更新',
                'seq'          => 200,
            ],
        ])->assertOk()
          ->assertJsonCount(1, 'updated')
          ->assertJsonCount(0, 'created');

        $this->assertDatabaseHas('expenses', [
            'id'           => $expense->id,
            'station_from' => '天王寺',
            'station_to'   => '難波',
            'cost'         => 500,
            'trip_type'    => 'one_way',
        ]);
    }

    /**
     * シナリオ 3: creates のみ（updates は空）を送信すると 200 が返り新しい行が挿入される
     *
     * - expense_report_id / expense_date / trip_type がそのまま保存される
     * - レスポンスの created 配列に1件含まれる
     * - DB に新レコードが存在する
     */
    public function test_creates_only_returns_200_and_inserts_row(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $date = now()->setYear($report->year)->setMonth($report->month)->startOfMonth()->toDateString();

        $this->putBatch($user, $report, creates: [
            [
                'expense_date' => $date,
                'seq'          => 1024,
                'station_from' => '難波',
                'station_to'   => '新大阪',
                'cost'         => 320,
                'trip_type'    => 'round_trip',
                'category'     => 'regular',
            ],
        ])->assertOk()
          ->assertJsonCount(0, 'updated')
          ->assertJsonCount(1, 'created');

        $this->assertDatabaseHas('expenses', [
            'expense_report_id' => $report->id,
            'expense_date'      => $date,
            'station_from'      => '難波',
            'station_to'        => '新大阪',
            'cost'              => 320,
        ]);
    }

    /**
     * シナリオ 4: updates と creates が混在しても 200 が返り両方 DB に反映される
     *
     * - 既存1行の更新 + 新規1行の作成
     * - updated / created がそれぞれ1件ずつ返る
     */
    public function test_mixed_updates_and_creates_both_persist(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $existing = $this->makeExpense($report);
        $newDate  = now()->setYear($report->year)->setMonth($report->month)->startOfMonth()
                         ->addDay()->toDateString();

        $this->putBatch(
            $user,
            $report,
            updates: [
                ['id' => $existing->id, 'station_from' => '桃谷', 'station_to' => '鶴橋',
                 'cost' => 180, 'trip_type' => 'one_way', 'seq' => 100],
            ],
            creates: [
                ['expense_date' => $newDate, 'seq' => 2048, 'station_from' => '鶴橋',
                 'station_to' => '天王寺', 'cost' => 210, 'trip_type' => 'round_trip',
                 'category' => 'regular'],
            ]
        )->assertOk()
         ->assertJsonCount(1, 'updated')
         ->assertJsonCount(1, 'created');

        $this->assertDatabaseHas('expenses', ['id' => $existing->id, 'station_from' => '桃谷']);
        $this->assertDatabaseHas('expenses', ['expense_report_id' => $report->id, 'expense_date' => $newDate]);
    }

    /**
     * シナリオ 5: updates / creates が両方空配列でも 200 が返り DB は変化しない
     *
     * - 「変更なしのSave」を誤ってリクエストした場合も安全に処理される
     */
    public function test_empty_updates_and_creates_returns_200_with_no_db_change(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $expense = $this->makeExpense($report);

        $this->putBatch($user, $report)
             ->assertOk()
             ->assertJson(['updated' => [], 'created' => []]);

        // 既存行は変化していない
        $this->assertDatabaseHas('expenses', [
            'id'      => $expense->id,
            'cost'    => $expense->cost,
        ]);
    }

    // =========================================================================
    // 6〜7. セキュリティ - 他人のレポート / ID混入
    // =========================================================================

    /**
     * シナリオ 6: 他人が所有するレポートへのアクセスは welcome へリダイレクト（302）
     *
     * - report.user_id と actingAs が一致しない場合
     * - このアプリの例外ハンドラは AuthorizationException を
     *   JSON リクエストでも常に welcome へリダイレクトする
     */
    public function test_cannot_batch_save_other_users_report(): void
    {
        [$owner, $report] = $this->makeUserWithReport();
        $attacker = User::factory()->general()->create();

        $this->actingAs($attacker)->putJson(route('api.expenses.batch'), [
            'report_id' => $report->id,
            'updates'   => [],
            'creates'   => [],
        ])->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 7: updates[*].id に別レポートの expense ID を混入すると 403
     *
     * - 正当な report_id を持ちつつ、他人の expense ID を updates に含めた場合
     * - コントローラの「全 ID がこのレポートに属するか」チェックで検出される
     */
    public function test_cannot_update_expense_belonging_to_another_report(): void
    {
        [$user, $report] = $this->makeUserWithReport();

        // 別ユーザーのレポートと expense を作成
        [$otherUser, $otherReport] = $this->makeUserWithReport();
        $otherExpense = $this->makeExpense($otherReport);

        $this->putBatch($user, $report, updates: [
            [
                'id'           => $otherExpense->id, // 他人の expense ID
                'station_from' => '不正な変更',
                'station_to'   => '不正な変更',
                'cost'         => 9999,
                'trip_type'    => 'one_way',
                'seq'          => 100,
            ],
        ])->assertForbidden();

        // 他人の expense は変化していない
        $this->assertDatabaseHas('expenses', [
            'id'   => $otherExpense->id,
            'cost' => $otherExpense->cost,
        ]);
    }

    // =========================================================================
    // 8〜9. ロック
    // =========================================================================

    /**
     * シナリオ 8: SUBMITTED 状態のレポートへのアクセスは 423
     *
     * - 提出済みレポートは編集不可（ロック）
     */
    public function test_submitted_report_returns_423(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $report->update(['status' => ExpenseReportStatus::SUBMITTED]);

        $this->putBatch($user, $report)
             ->assertStatus(423);
    }

    /**
     * シナリオ 9: APPROVED 状態のレポートへのアクセスは 423
     *
     * - 承認済みレポートも同様にロックされている
     */
    public function test_approved_report_returns_423(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $report->update(['status' => ExpenseReportStatus::APPROVED]);

        $this->putBatch($user, $report)
             ->assertStatus(423);
    }

    // =========================================================================
    // 10〜13. バリデーション
    // =========================================================================

    /**
     * シナリオ 10: report_id に存在しないIDを指定すると 422
     */
    public function test_invalid_report_id_returns_422(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)->putJson(route('api.expenses.batch'), [
            'report_id' => 999999,
            'updates'   => [],
            'creates'   => [],
        ])->assertUnprocessable();
    }

    /**
     * シナリオ 11: creates[*].expense_date が対象月の範囲外なら 422
     *
     * - レポートが今月のものに対して、翌月の日付を creates に含めた場合
     */
    public function test_creates_with_out_of_month_date_returns_422(): void
    {
        [$user, $report] = $this->makeUserWithReport();

        // 翌月の日付
        $nextMonthDate = now()->setYear($report->year)->setMonth($report->month)
                              ->addMonth()->startOfMonth()->toDateString();

        $this->putBatch($user, $report, creates: [
            [
                'expense_date' => $nextMonthDate,
                'seq'          => 100,
                'station_from' => '梅田',
                'station_to'   => '難波',
                'cost'         => 240,
                'trip_type'    => 'round_trip',
                'category'     => 'regular',
            ],
        ])->assertUnprocessable();
    }

    /**
     * シナリオ 12: creates[*].trip_type が欠如している場合は 422
     *
     * - trip_type は creates において required
     */
    public function test_creates_without_trip_type_returns_422(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $date = now()->setYear($report->year)->setMonth($report->month)->startOfMonth()->toDateString();

        $this->putBatch($user, $report, creates: [
            [
                'expense_date' => $date,
                'seq'          => 100,
                'station_from' => '梅田',
                'station_to'   => '難波',
                'cost'         => 240,
                // trip_type を意図的に省略
                'category'     => 'regular',
            ],
        ])->assertUnprocessable();
    }

    /**
     * シナリオ 13: updates[*].id が未指定の場合は 422
     *
     * - id は updates において required
     */
    public function test_updates_without_id_returns_422(): void
    {
        [$user, $report] = $this->makeUserWithReport();

        $this->putBatch($user, $report, updates: [
            [
                // id を意図的に省略
                'station_from' => '梅田',
                'station_to'   => '難波',
                'cost'         => 240,
                'trip_type'    => 'round_trip',
                'seq'          => 100,
            ],
        ])->assertUnprocessable();
    }

    // =========================================================================
    // 14〜15. DB 反映の詳細確認
    // =========================================================================

    /**
     * シナリオ 14: updates 後の各フィールド値が正しく DB に保存される
     *
     * - station_from / station_to / cost / trip_type / note / seq の全フィールドを確認
     */
    public function test_update_persists_all_fields_correctly(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $expense = $this->makeExpense($report, ['cost' => 100, 'note' => null]);

        $this->putBatch($user, $report, updates: [
            [
                'id'           => $expense->id,
                'station_from' => '新大阪',
                'station_to'   => '三ノ宮',
                'cost'         => 680,
                'trip_type'    => 'one_way',
                'note'         => '出張',
                'seq'          => 512,
            ],
        ])->assertOk();

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
     * シナリオ 15: creates 後のレコードが expense_report_id / expense_date で正しく紐づく
     *
     * - レスポンスの created[0] に id が含まれる
     * - DB にレコードが存在し、expense_report_id が report.id に一致する
     * - creates した行数分だけレコードが増える
     */
    public function test_create_persists_with_correct_report_association(): void
    {
        [$user, $report] = $this->makeUserWithReport();
        $date = now()->setYear($report->year)->setMonth($report->month)->startOfMonth()->toDateString();

        $beforeCount = Expense::where('expense_report_id', $report->id)->count();

        $response = $this->putBatch($user, $report, creates: [
            ['expense_date' => $date, 'seq' => 100, 'station_from' => 'A', 'station_to' => 'B',
             'cost' => 160, 'trip_type' => 'one_way', 'category' => 'regular'],
            ['expense_date' => $date, 'seq' => 200, 'station_from' => 'C', 'station_to' => 'D',
             'cost' => 320, 'trip_type' => 'round_trip', 'category' => 'regular'],
        ]);

        $response->assertOk()->assertJsonCount(2, 'created');

        $afterCount = Expense::where('expense_report_id', $report->id)->count();
        $this->assertSame($beforeCount + 2, $afterCount);

        // レスポンスの id を使って DB にレコードが存在することを確認
        $createdId = $response->json('created.0.id');
        $this->assertDatabaseHas('expenses', [
            'id'                => $createdId,
            'expense_report_id' => $report->id,
        ]);
    }
}
