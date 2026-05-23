<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\Event;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * EventAssignController のテスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [ゲスト]
 *   1. 未認証アクセスはログインページへリダイレクト
 *
 * [権限: general ユーザー]
 *   2. shift_assigner (edit) を表示しようとすると 403
 *   3. store (新規作成) しようとすると 403
 *   4. update (更新) しようとすると 403
 *   5. destroy (削除) しようとすると 403
 *   6. copy (複製) しようとすると 403
 *
 * [権限: admin ユーザー（正常系）]
 *   7. shift_assigner (edit) を表示できる → 200
 *   8. store でスコープ内に Event が作成される → DB確認
 *   9. update でスコープ内の Event を更新できる → DB確認
 *  10. destroy でスコープ内の Event を削除できる → DB確認
 *  11. copy で Event を複製できる → DB確認・district/department が引き継がれる
 *
 * [スコープ分離]
 *  12. 別スコープの Event は update できない → 403
 *  13. 別スコープの Event は destroy できない → 403
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * テストのセットアップ方針
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - RefreshDatabase でテストごとに DB をリセット
 * - TestCase::setUp() で RoleSeeder が実行済み（admin/super_admin/general ロール作成）
 * - makeAdmin() で admin ユーザー + UserManagementScope を生成
 * - CurrentScopeService は session('selected_scope_id') でスコープを解決するため、
 *   withSession(['selected_scope_id' => $scope->id]) を各リクエストに付与する
 */
class EventAssignControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // ヘルパー
    // =========================================================================

    /**
     * admin ユーザー + District + Department + UserManagementScope を一括生成する。
     *
     * CurrentScopeService が session('selected_scope_id') で解決するため、
     * テストでは withSession(['selected_scope_id' => $scope->id]) と組み合わせて使う。
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
     * 別スコープ（admin と無関係な district / department）の Event を作成する。
     * スコープ分離テストで「操作できないはずのデータ」として使う。
     */
    private function makeEventOutsideScope(): Event
    {
        $otherDistrict   = District::factory()->create();
        $otherDepartment = Department::factory()->create();

        return Event::factory()->create([
            'district_id'   => $otherDistrict->id,
            'department_id' => $otherDepartment->id,
            'event_date'    => now()->toDateString(),
            'status'        => 'pending',
            'type'          => 'regular_time',
        ]);
    }

    // =========================================================================
    // 1. ゲスト
    // =========================================================================

    /**
     * シナリオ 1: 未認証ユーザーは shift_assigner にアクセスするとログインへリダイレクト
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('calendar.edit'))->assertRedirect(route('login'));
    }

    // =========================================================================
    // 2〜6. general ユーザー（全アクション 403）
    // =========================================================================

    /**
     * シナリオ 2: general ユーザーは shift_assigner を表示できない
     */
    public function test_general_user_cannot_view_shift_assigner(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)
            ->get(route('calendar.edit'))
            ->assertForbidden();
    }

    /**
     * シナリオ 3: general ユーザーは Event を新規作成できない
     */
    public function test_general_user_cannot_store_event(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)
            ->post(route('events.store'), ['event_date' => now()->toDateString()])
            ->assertForbidden();
    }

    /**
     * シナリオ 4: general ユーザーは Event を更新できない
     */
    public function test_general_user_cannot_update_event(): void
    {
        $user  = User::factory()->general()->create();
        $event = Event::factory()->create([
            'event_date' => now()->toDateString(),
            'status'     => 'pending',
            'type'       => 'regular_time',
        ]);

        $this->actingAs($user)
            ->put(route('events.update', $event), [
                'event_date' => now()->toDateString(),
                'status'     => 'fixed',
                'type'       => 'regular_time',
            ])
            ->assertForbidden();
    }

    /**
     * シナリオ 5: general ユーザーは Event を削除できない
     */
    public function test_general_user_cannot_delete_event(): void
    {
        $user  = User::factory()->general()->create();
        $event = Event::factory()->create([
            'event_date' => now()->toDateString(),
            'status'     => 'pending',
            'type'       => 'regular_time',
        ]);

        $this->actingAs($user)
            ->delete(route('events.destroy', $event))
            ->assertForbidden();
    }

    /**
     * シナリオ 6: general ユーザーは Event を複製できない
     */
    public function test_general_user_cannot_copy_event(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)
            ->post(route('events.copy'), [
                'event_date' => now()->toDateString(),
                'status'     => 'pending',
                'type'       => 'regular_time',
            ])
            ->assertForbidden();
    }

    // =========================================================================
    // 7. admin: 一覧表示 (edit)
    // =========================================================================

    /**
     * シナリオ 7: admin はスコープを選択した状態で shift_assigner を表示できる
     *
     * - EventPolicy::viewAny は isAdmin() のみ確認
     * - CurrentScopeService が session('selected_scope_id') でスコープを解決する
     */
    public function test_admin_can_view_shift_assigner(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('calendar.edit'))
            ->assertOk();
    }

    // =========================================================================
    // 8. admin: store（新規作成）
    // =========================================================================

    /**
     * シナリオ 8: admin は Event を新規作成できる
     *
     * - district_id / department_id が CurrentScopeService のスコープから自動セットされること
     * - status=pending / type=regular_time で作成されること
     */
    public function test_admin_can_store_event(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $date = now()->toDateString();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->post(route('events.store'), ['event_date' => $date])
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'event_date'    => $date,
            'status'        => 'pending',
            'type'          => 'regular_time',
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);
    }

    // =========================================================================
    // 9. admin: update（更新）
    // =========================================================================

    /**
     * シナリオ 9: admin はスコープ内の Event を更新できる
     *
     * - EventPolicy::update → canManage() が district_id / department_id の一致を確認
     * - status が pending → fixed に変わること
     */
    public function test_admin_can_update_event_in_scope(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $event = Event::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
            'event_date'    => now()->toDateString(),
            'status'        => 'pending',
            'type'          => 'regular_time',
        ]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->put(route('events.update', $event), [
                'event_date' => now()->toDateString(),
                'status'     => 'fixed',
                'type'       => 'regular_time',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'id'     => $event->id,
            'status' => 'fixed',
        ]);
    }

    /**
     * シナリオ 9b: school_name / start_time / end_time を更新できる
     *
     * - total_duration は start_time / end_time から自動計算されること (09:00〜17:00 → 8:00)
     */
    public function test_admin_can_update_event_fields_and_duration_is_calculated(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $event = Event::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
            'event_date'    => now()->toDateString(),
            'status'        => 'pending',
            'type'          => 'regular_time',
        ]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->put(route('events.update', $event), [
                'event_date'  => now()->toDateString(),
                'status'      => 'pending',
                'type'        => 'regular_time',
                'school_name' => 'Test School',
                'start_time'  => '09:00',
                'end_time'    => '17:00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'id'             => $event->id,
            'school_name'    => 'Test School',
            'total_duration' => '8:00',
        ]);
    }

    // =========================================================================
    // 10. admin: destroy（削除）
    // =========================================================================

    /**
     * シナリオ 10: admin はスコープ内の Event を削除できる
     *
     * - 削除後に DB にレコードが存在しないこと
     */
    public function test_admin_can_delete_event_in_scope(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $event = Event::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
            'event_date'    => now()->toDateString(),
            'status'        => 'pending',
            'type'          => 'regular_time',
        ]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->delete(route('events.destroy', $event))
            ->assertRedirect();

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    // =========================================================================
    // 11. admin: copy（複製）
    // =========================================================================

    /**
     * シナリオ 11: admin は Event を複製できる
     *
     * - 複製先の日付で新規 Event が作成されること
     * - district_id / department_id がスコープから自動セットされること
     * - original_user_id / assigned_user_id は null でも通ること（nullable）
     */
    public function test_admin_can_copy_event(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $newDate = now()->addDay()->toDateString();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->post(route('events.copy'), [
                'event_date'  => $newDate,
                'status'      => 'pending',
                'type'        => 'regular_time',
                'school_name' => 'Copied School',
                'start_time'  => '10:00',
                'end_time'    => '12:00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'event_date'    => $newDate,
            'school_name'   => 'Copied School',
            'district_id'   => $district->id,
            'department_id' => $department->id,
            // 10:00〜12:00 → total_duration が 2:00 に自動計算される
            'total_duration' => '2:00',
        ]);
    }

    /**
     * シナリオ 11b: copy で total_duration を手動指定した場合はそのまま保存される
     */
    public function test_admin_can_copy_event_with_explicit_duration(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $newDate = now()->addDay()->toDateString();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->post(route('events.copy'), [
                'event_date'     => $newDate,
                'status'         => 'pending',
                'type'           => 'overtime',
                'total_duration' => '3:30',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'event_date'     => $newDate,
            'type'           => 'overtime',
            'total_duration' => '3:30',
            'district_id'    => $district->id,
            'department_id'  => $department->id,
        ]);
    }

    // =========================================================================
    // 12〜13. スコープ分離（別 district/department のデータは操作不可）
    // =========================================================================

    /**
     * シナリオ 12: admin は別スコープの Event を更新できない
     *
     * - EventPolicy::canManage が district_id / department_id の不一致を検出して 403
     */
    public function test_admin_cannot_update_event_outside_scope(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $otherEvent = $this->makeEventOutsideScope();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->put(route('events.update', $otherEvent), [
                'event_date' => now()->toDateString(),
                'status'     => 'fixed',
                'type'       => 'regular_time',
            ])
            ->assertForbidden();

        // DB が更新されていないこと
        $this->assertDatabaseHas('events', [
            'id'     => $otherEvent->id,
            'status' => 'pending',
        ]);
    }

    /**
     * シナリオ 13: admin は別スコープの Event を削除できない
     *
     * - EventPolicy::canManage が district_id / department_id の不一致を検出して 403
     */
    public function test_admin_cannot_delete_event_outside_scope(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $otherEvent = $this->makeEventOutsideScope();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->delete(route('events.destroy', $otherEvent))
            ->assertForbidden();

        // DB にレコードが残っていること
        $this->assertDatabaseHas('events', ['id' => $otherEvent->id]);
    }
}
