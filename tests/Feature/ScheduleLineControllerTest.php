<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\ScheduleLine;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ScheduleLineController のテスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [ゲスト]
 *   1. 未認証アクセスはログインページへリダイレクト
 *
 * [権限: general ユーザー]
 *   2. edit (schedule_manager) を表示しようとすると welcome へリダイレクト
 *   3. store しようとすると welcome へリダイレクト
 *   4. update しようとすると welcome へリダイレクト
 *   5. bulkUpdate しようとすると welcome へリダイレクト
 *   6. destroy しようとすると welcome へリダイレクト
 *   7. copy しようとすると welcome へリダイレクト
 *
 * [権限: admin ユーザー（正常系）]
 *   8. edit を表示できる → 200
 *   9. store でスコープ内に ScheduleLine が作成される → JSON + DB確認
 *  10. update でスコープ内の ScheduleLine を更新できる → redirect + DB確認
 *  11. bulkUpdate で複数 ScheduleLine を一括更新できる → JSON + DB確認
 *  12. destroy でスコープ内の ScheduleLine を削除できる → JSON + DB確認
 *  13. copy で ScheduleLine を複製できる → JSON + DB確認
 *
 * [スコープ分離]
 *  14. 別 district の ScheduleLine は update できない → welcome へリダイレクト
 *  15. 別 district の ScheduleLine は destroy できない → welcome へリダイレクト
 *  16. 別 district の ScheduleLine は copy できない → welcome へリダイレクト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ポリシー仕様メモ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - ScheduleLinePolicy::canManage → isAdmin() && line.district_id === currentDistrictId()
 *   ※ Leave/Event と異なり department_id は判定に含まない
 * - store / edit / bulkUpdate は viewAny / create (isAdmin() のみ) で保護
 * - update / delete / copy は canManage (district_id 一致) で保護
 * - store / destroy / bulkUpdate / copy は JSON レスポンスを返す
 * - update は back() でリダイレクトを返す
 */
class ScheduleLineControllerTest extends TestCase
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
     * スコープ内の ScheduleLine を生成する。
     * canManage は district_id のみで判定するため、district_id を一致させる。
     */
    private function makeLineInScope(District $district, Department $department): ScheduleLine
    {
        return ScheduleLine::factory()->create([
            'user_id'         => null,
            'dow'             => 1,
            'school_name'     => 'In-Scope School',
            'start_time'      => '09:00:00',
            'end_time'        => '17:00:00',
            'effective_start' => now()->subMonth()->toDateString(),
            'effective_end'   => now()->addMonth()->toDateString(),
            'district_id'     => $district->id,
            'department_id'   => $department->id,
        ]);
    }

    /**
     * 別スコープ（admin と無関係な district）の ScheduleLine を生成する。
     * canManage は district_id のみ確認するため、別 district で十分。
     */
    private function makeLineOutsideScope(): ScheduleLine
    {
        $otherDistrict   = District::factory()->create();
        $otherDepartment = Department::factory()->create();

        return ScheduleLine::factory()->create([
            'user_id'         => null,
            'district_id'     => $otherDistrict->id,
            'department_id'   => $otherDepartment->id,
        ]);
    }

    // =========================================================================
    // 1. ゲスト
    // =========================================================================

    /**
     * シナリオ 1: 未認証ユーザーは schedule_manager にアクセスするとログインへリダイレクト
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('schedules.edit'))->assertRedirect(route('login'));
    }

    // =========================================================================
    // 2〜7. general ユーザー（全アクション welcome へリダイレクト）
    // =========================================================================

    /**
     * シナリオ 2: general ユーザーは schedule_manager を表示できず welcome へリダイレクト
     */
    public function test_general_user_cannot_view_schedule_manager(): void
    {
        $this->actingAs(User::factory()->general()->create())
            ->get(route('schedules.edit'))
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 3: general ユーザーは ScheduleLine を新規作成できず welcome へリダイレクト
     */
    public function test_general_user_cannot_store_schedule_line(): void
    {
        $this->actingAs(User::factory()->general()->create())
            ->postJson(route('schedule_lines.store'), ['dow' => 1])
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 4: general ユーザーは ScheduleLine を更新できず welcome へリダイレクト
     */
    public function test_general_user_cannot_update_schedule_line(): void
    {
        $line = ScheduleLine::factory()->create();

        $this->actingAs(User::factory()->general()->create())
            ->put(route('schedule_lines.update', $line), [
                'dow'             => 1,
                'school_name'     => 'Test',
                'start_time'      => '09:00',
                'end_time'        => '17:00',
                'effective_start' => now()->toDateString(),
                'effective_end'   => now()->addMonth()->toDateString(),
            ])
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 5: general ユーザーは ScheduleLine を一括更新できず welcome へリダイレクト
     */
    public function test_general_user_cannot_bulk_update_schedule_lines(): void
    {
        $this->actingAs(User::factory()->general()->create())
            ->postJson(route('schedule_lines.bulk_update'), ['items' => []])
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 6: general ユーザーは ScheduleLine を削除できず welcome へリダイレクト
     */
    public function test_general_user_cannot_destroy_schedule_line(): void
    {
        $line = ScheduleLine::factory()->create();

        $this->actingAs(User::factory()->general()->create())
            ->deleteJson(route('schedule_lines.destroy', $line))
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 7: general ユーザーは ScheduleLine を複製できず welcome へリダイレクト
     */
    public function test_general_user_cannot_copy_schedule_line(): void
    {
        $line = ScheduleLine::factory()->create();

        $this->actingAs(User::factory()->general()->create())
            ->postJson(route('schedule_lines.copy', $line), [
                'effective_start' => now()->addDay()->toDateString(),
                'effective_end'   => now()->addYear()->toDateString(),
            ])
            ->assertRedirect(route('welcome'));
    }

    // =========================================================================
    // 8. admin: edit（一覧表示）
    // =========================================================================

    /**
     * シナリオ 8: admin はスコープを選択した状態で schedule_manager を表示できる
     */
    public function test_admin_can_view_schedule_manager(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('schedules.edit'))
            ->assertOk();
    }

    // =========================================================================
    // 9. admin: store（新規作成）
    // =========================================================================

    /**
     * シナリオ 9: admin は ScheduleLine を新規作成できる
     *
     * - JSON レスポンスで ok=true / line_id が返ること
     * - district_id / department_id がスコープから自動セットされること
     * - 初期値（start_time/end_time=00:00:00）で作成されること
     */
    public function test_admin_can_store_schedule_line(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $response = $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('schedule_lines.store'), [
                'user_id'     => null,
                'school_name' => 'New School',
                'dow'         => 2,
            ])
            ->assertOk()
            ->assertJsonStructure(['ok', 'line_id']);

        $lineId = $response->json('line_id');

        $this->assertDatabaseHas('schedule_lines', [
            'id'            => $lineId,
            'dow'           => 2,
            'start_time'    => '00:00:00',
            'end_time'      => '00:00:00',
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);
    }

    // =========================================================================
    // 10. admin: update（更新）
    // =========================================================================

    /**
     * シナリオ 10: admin はスコープ内の ScheduleLine を更新できる
     *
     * - ScheduleLinePolicy::canManage → isAdmin() && district_id 一致
     * - school_name / dow が変更されること
     */
    public function test_admin_can_update_schedule_line_in_scope(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $line = $this->makeLineInScope($district, $department);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->put(route('schedule_lines.update', $line), [
                'user_id'         => null,
                'dow'             => 3,
                'school_name'     => 'Updated School',
                'start_time'      => '10:00',
                'end_time'        => '18:00',
                'effective_start' => now()->toDateString(),
                'effective_end'   => now()->addMonth()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schedule_lines', [
            'id'          => $line->id,
            'dow'         => 3,
            'school_name' => 'Updated School',
        ]);
    }

    // =========================================================================
    // 11. admin: bulkUpdate（一括更新）
    // =========================================================================

    /**
     * シナリオ 11: admin は複数の ScheduleLine を一括更新できる
     *
     * - JSON レスポンスで ok=true / updated=1 が返ること
     * - school_name が変更されること
     * - start_time は H:i で送り H:i:s として保存されること
     */
    public function test_admin_can_bulk_update_schedule_lines(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $line = $this->makeLineInScope($district, $department);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('schedule_lines.bulk_update'), [
                'items' => [[
                    'id'             => $line->id,
                    'user_id'        => null,
                    'dow'            => 1,
                    'school_name'    => 'Bulk Updated School',
                    'start_time'     => '09:00',
                    'end_time'       => '17:00',
                    'effective_start' => now()->subMonth()->toDateString(),
                    'effective_end'  => now()->addMonth()->toDateString(),
                ]],
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'updated' => 1]);

        $this->assertDatabaseHas('schedule_lines', [
            'id'          => $line->id,
            'school_name' => 'Bulk Updated School',
            'start_time'  => '09:00:00',
        ]);
    }

    // =========================================================================
    // 12. admin: destroy（削除）
    // =========================================================================

    /**
     * シナリオ 12: admin はスコープ内の ScheduleLine を削除できる
     *
     * - JSON レスポンスで ok=true / id が返ること
     * - DB からレコードが消えること
     */
    public function test_admin_can_destroy_schedule_line_in_scope(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $line = $this->makeLineInScope($district, $department);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->deleteJson(route('schedule_lines.destroy', $line))
            ->assertOk()
            ->assertJson(['ok' => true, 'id' => $line->id]);

        $this->assertDatabaseMissing('schedule_lines', ['id' => $line->id]);
    }

    // =========================================================================
    // 13. admin: copy（複製）
    // =========================================================================

    /**
     * シナリオ 13: admin は ScheduleLine を複製できる
     *
     * - JSON レスポンスで ok=true / new_id が返ること
     * - 新規 ScheduleLine が parent_line_id 付きで作成されること
     * - 期間が重複しない日付を指定すれば元行はクローズされない
     */
    public function test_admin_can_copy_schedule_line(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        // 元行は過去期間（クローズ条件 lineEnd >= newStart を満たさない）
        $line = ScheduleLine::factory()->create([
            'user_id'         => null,
            'dow'             => 1,
            'school_name'     => 'Copy Source School',
            'start_time'      => '09:00:00',
            'end_time'        => '17:00:00',
            'effective_start' => now()->subYear()->toDateString(),
            'effective_end'   => now()->toDateString(),
            'district_id'     => $district->id,
            'department_id'   => $department->id,
        ]);

        $newStart = now()->addDay()->toDateString();
        $newEnd   = now()->addYear()->toDateString();

        $response = $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('schedule_lines.copy', $line), [
                'effective_start' => $newStart,
                'effective_end'   => $newEnd,
                'user_id'         => null,
            ])
            ->assertOk()
            ->assertJsonStructure(['ok', 'new_id']);

        $newId = $response->json('new_id');

        $this->assertDatabaseHas('schedule_lines', [
            'id'              => $newId,
            'parent_line_id'  => $line->id,
            'effective_start' => $newStart,
            'effective_end'   => $newEnd,
        ]);
    }

    // =========================================================================
    // 14〜16. スコープ分離（別 district のデータは操作不可）
    // =========================================================================

    /**
     * シナリオ 14: admin は別 district の ScheduleLine を更新できない
     *
     * - canManage が district_id の不一致を検出して welcome へリダイレクト
     * - DB が更新されていないこと
     */
    public function test_admin_cannot_update_schedule_line_outside_scope(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $otherLine = $this->makeLineOutsideScope();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->put(route('schedule_lines.update', $otherLine), [
                'user_id'         => null,
                'dow'             => 3,
                'school_name'     => 'Hijacked School',
                'start_time'      => '10:00',
                'end_time'        => '18:00',
                'effective_start' => now()->toDateString(),
                'effective_end'   => now()->addMonth()->toDateString(),
            ])
            ->assertRedirect(route('welcome'));

        $this->assertDatabaseHas('schedule_lines', [
            'id'          => $otherLine->id,
            'school_name' => $otherLine->school_name,
        ]);
    }

    /**
     * シナリオ 15: admin は別 district の ScheduleLine を削除できない
     *
     * - canManage が district_id の不一致を検出して welcome へリダイレクト
     * - DB にレコードが残っていること
     */
    public function test_admin_cannot_destroy_schedule_line_outside_scope(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $otherLine = $this->makeLineOutsideScope();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->deleteJson(route('schedule_lines.destroy', $otherLine))
            ->assertRedirect(route('welcome'));

        $this->assertDatabaseHas('schedule_lines', ['id' => $otherLine->id]);
    }

    /**
     * シナリオ 16: admin は別 district の ScheduleLine を複製できない
     *
     * - canManage が district_id の不一致を検出して welcome へリダイレクト
     */
    public function test_admin_cannot_copy_schedule_line_outside_scope(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $otherLine = $this->makeLineOutsideScope();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('schedule_lines.copy', $otherLine), [
                'effective_start' => now()->addDay()->toDateString(),
                'effective_end'   => now()->addYear()->toDateString(),
                'user_id'         => null,
            ])
            ->assertRedirect(route('welcome'));

        // 新規ラインが作成されていないこと
        $this->assertDatabaseMissing('schedule_lines', [
            'parent_line_id' => $otherLine->id,
        ]);
    }
}
