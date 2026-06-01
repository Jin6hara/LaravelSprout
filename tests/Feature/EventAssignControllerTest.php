<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\Event;
use App\Models\Leave;
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
 *   2. shift_assigner (edit) を表示しようとすると welcome へリダイレクト
 *   3. store (新規作成) しようとすると welcome へリダイレクト
 *   4. update (更新) しようとすると welcome へリダイレクト
 *   5. destroy (削除) しようとすると welcome へリダイレクト
 *   6. copy (複製) しようとすると welcome へリダイレクト
 *
 * [権限: admin ユーザー（正常系）]
 *   7. shift_assigner (edit) を表示できる → 200
 *   7b. daily shift board を表示できる → 200
 *   8. store でスコープ内に Event が作成される → DB確認
 *   9. update でスコープ内の Event を更新できる → DB確認
 *  10. destroy でスコープ内の Event を削除できる → DB確認
 *  11. copy で Event を複製できる → DB確認・district/department が引き継がれる
 *
 * [スコープ分離]
 *  12. 別スコープの Event は update できない → welcome へリダイレクト
 *  13. 別スコープの Event は destroy できない → welcome へリダイレクト
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
    // 2〜6. general ユーザー（全アクション welcome へリダイレクト）
    // =========================================================================

    /**
     * シナリオ 2: general ユーザーは shift_assigner を表示できず welcome へリダイレクト
     */
    public function test_general_user_cannot_view_shift_assigner(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)
            ->get(route('calendar.edit'))
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 2b: general ユーザーは daily shift board を表示できず welcome へリダイレクト
     */
    public function test_general_user_cannot_view_daily_shift_board(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)
            ->get(route('calendar.daily_assigner'))
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 3: general ユーザーは Event を新規作成できず welcome へリダイレクト
     */
    public function test_general_user_cannot_store_event(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)
            ->post(route('events.store'), ['event_date' => now()->toDateString()])
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 4: general ユーザーは Event を更新できず welcome へリダイレクト
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
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 5: general ユーザーは Event を削除できず welcome へリダイレクト
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
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 6: general ユーザーは Event を複製できず welcome へリダイレクト
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
            ->assertRedirect(route('welcome'));
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
            ->assertOk()
            ->assertSee('+ Add Shift')
            ->assertSee('New Shift')
            ->assertSee('Copy Shift')
            ->assertSee(asset('js/user-autocomplete.js'))
            ->assertSee(asset('js/shift-copy-modal.js'));
    }

    /**
     * シナリオ 7b: admin は指定日の daily shift board を表示できる
     */
    public function test_admin_can_view_daily_shift_board(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        Event::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
            'event_date'    => '2026-05-31',
            'status'        => 'pending',
            'type'          => 'regular_time',
            'school_name'   => 'Scope School',
        ]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('calendar.daily_assigner', ['date' => '2026-05-31']))
            ->assertOk()
            ->assertSee('Daily Shift Board')
            ->assertSee('Scope School')
            ->assertSee('Create Absence')
            ->assertSee('+ Add Shift')
            ->assertSee('New Shift for 2026-05-31')
            ->assertSee('Copy Shift')
            ->assertSee('Delete')
            ->assertSee('Delete Confirmation')
            ->assertSee(asset('js/shift-copy-modal.js'))
            ->assertSee(route('api.users.search'))
            ->assertSee('Tentative Sublist Preview')
            ->assertSee('Master Sublist Preview')
            ->assertSee('Final Sublist Preview')
            ->assertSee(route('calendar.edit.pdf.preview', [
                'mode' => 'tentative',
                'event_date' => '2026-05-31',
            ]));
    }

    // =========================================================================
    // 8. admin: store（新規作成）
    // =========================================================================

    /**
     * シナリオ 8: admin は Event を新規作成できる
     *
     * - district_id / department_id はリクエストでは受け付けず、CurrentScopeService が強制セットする
     *   → リクエストに含めなくても正しいスコープ値で保存されることを確認
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

    /**
     * シナリオ 8b: 入力モーダル用の項目付き Event を作成できる
     */
    public function test_admin_can_store_event_from_create_modal_payload(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $originalUser = User::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
            'first_name'    => 'Original',
            'family_name'   => 'Teacher',
            'employee_code' => 'O12345',
        ]);
        $assignedUser = User::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
            'first_name'    => 'Assigned',
            'family_name'   => 'Teacher',
            'employee_code' => 'A12345',
        ]);

        $date = '2026-05-31';

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->post(route('events.store'), [
                'event_date'           => $date,
                'title'                => 'Cover Shift',
                'original_user_lookup' => 'Original Teacher [O12345]',
                'assigned_user_lookup' => 'Assigned Teacher [A12345]',
                'school_name'          => 'Modal School',
                'start_time'           => '09:00',
                'end_time'             => '12:30',
                'Lesson'               => 'G1 Math',
                'type'                 => 'regular_time',
                'status'               => 'pending',
                'notes'                => 'Created from modal',
            ])
            ->assertRedirect(route('calendar.edit', ['event_date' => $date]));

        $this->assertDatabaseHas('events', [
            'event_date'       => $date,
            'title'            => 'Cover Shift',
            'original_user_id' => $originalUser->id,
            'assigned_user_id' => $assignedUser->id,
            'school_name'      => 'Modal School',
            'start_time'       => '09:00:00',
            'end_time'         => '12:30:00',
            'total_duration'   => '3:30',
            'Lesson'           => 'G1 Math',
            'type'             => 'regular_time',
            'status'           => 'pending',
            'notes'            => 'Created from modal',
            'district_id'      => $district->id,
            'department_id'    => $department->id,
        ]);
    }

    /**
     * シナリオ 7c: daily shift board から選択日の Absence を作成でき、Daily に戻る
     */
    public function test_admin_can_create_absence_from_daily_shift_board(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $targetUser = User::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        $selectedDate = '2026-05-31';

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->post(route('leaves.store'), [
                'user_id'     => $targetUser->id,
                'start_date'  => $selectedDate,
                'kind'        => 'absence',
                'excused'     => 'unexcused',
                'status'      => 'pending',
                'redirect_to' => route('calendar.daily_assigner', ['date' => $selectedDate]),
            ])
            ->assertRedirect(route('calendar.daily_assigner', ['date' => $selectedDate]));

        $this->assertDatabaseHas('leaves', [
            'user_id'    => $targetUser->id,
            'start_date' => $selectedDate,
            'kind'       => 'absence',
            'status'     => 'pending',
        ]);
    }

    /**
     * シナリオ 7d: hidden user_id が空でも補完表示文字列からユーザーを特定して Absence を作成できる
     */
    public function test_daily_absence_create_resolves_user_from_lookup_text(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $targetUser = User::factory()->create([
            'district_id'    => $district->id,
            'department_id'  => $department->id,
            'first_name'     => 'Jane',
            'family_name'    => 'Teacher',
            'employee_code'  => 'T12345',
        ]);

        $selectedDate = '2026-05-31';

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->post(route('leaves.store'), [
                'user_id'     => '',
                'user_lookup' => 'Jane Teacher [T12345]',
                'start_date'  => $selectedDate,
                'kind'        => 'absence',
                'excused'     => 'unexcused',
                'status'      => 'pending',
                'redirect_to' => route('calendar.daily_assigner', ['date' => $selectedDate]),
            ])
            ->assertRedirect(route('calendar.daily_assigner', ['date' => $selectedDate]));

        $this->assertDatabaseHas('leaves', [
            'user_id'    => $targetUser->id,
            'start_date' => $selectedDate,
            'kind'       => 'absence',
        ]);
    }

    /**
     * シナリオ 7e: ユーザーを特定できない場合は Absence を作成せず Toast error を返す
     */
    public function test_daily_absence_create_returns_toast_error_when_target_user_is_not_found(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $selectedDate = '2026-05-31';

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->post(route('leaves.store'), [
                'user_id'     => '',
                'user_lookup' => 'Unknown User',
                'start_date'  => $selectedDate,
                'kind'        => 'absence',
                'excused'     => 'unexcused',
                'status'      => 'pending',
                'redirect_to' => route('calendar.daily_assigner', ['date' => $selectedDate]),
            ])
            ->assertRedirect(route('calendar.daily_assigner', ['date' => $selectedDate]))
            ->assertSessionHas('toast_errors', ['Target not found. Please select a user from the suggestions.']);

        $this->assertDatabaseCount('leaves', 0);
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

    /**
     * シナリオ 9c: daily shift board 用の一括更新 API で status を即時更新できる
     */
    public function test_admin_can_bulk_update_event_status_for_daily_shift_board(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $event = Event::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
            'event_date'    => '2026-05-31',
            'status'        => 'pending',
            'type'          => 'regular_time',
        ]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('events.bulk_update'), [
                'items' => [[
                    'id'         => $event->id,
                    'updated_at' => $event->updated_at?->format('Y-m-d H:i:s'),
                    'event_date' => '2026-05-31',
                    'status'     => 'fixed',
                    'type'       => 'regular_time',
                ]],
            ])
            ->assertOk()
            ->assertJson([
                'ok'      => true,
                'updated' => 1,
            ]);

        $this->assertDatabaseHas('events', [
            'id'     => $event->id,
            'status' => 'fixed',
        ]);
    }

    /**
     * シナリオ 9d: 古い画面からの一括更新は updated_at の不一致で拒否される
     */
    public function test_bulk_update_rejects_stale_event_payload(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $event = Event::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
            'event_date'    => '2026-05-31',
            'status'        => 'pending',
            'type'          => 'regular_time',
        ]);

        $staleUpdatedAt = $event->updated_at?->copy()->subMinute()->format('Y-m-d H:i:s');

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('events.bulk_update'), [
                'items' => [[
                    'id'         => $event->id,
                    'updated_at' => $staleUpdatedAt,
                    'event_date' => '2026-05-31',
                    'status'     => 'fixed',
                    'type'       => 'regular_time',
                ]],
            ])
            ->assertOk()
            ->assertJson([
                'ok'      => false,
                'updated' => 0,
                'failed'  => 1,
                'message' => 'Some shifts were already updated by others. Please reload before saving.',
            ])
            ->assertJsonPath('results.0.conflict', true);

        $this->assertDatabaseHas('events', [
            'id'     => $event->id,
            'status' => 'pending',
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

    /**
     * シナリオ 10b: admin でも Leave snapshot 由来の Event は直接削除できない
     *
     * - source_leave_id がある Event は Leave が正本なので Event 側から削除しない
     * - 削除したい場合は Absence / Leave 側を編集・削除する
     */
    public function test_admin_cannot_delete_event_managed_by_absence(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $leave = Leave::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
            'start_date'    => now()->toDateString(),
            'status'        => 'approved',
        ]);

        $event = Event::factory()->create([
            'district_id'     => $district->id,
            'department_id'   => $department->id,
            'event_date'      => now()->toDateString(),
            'status'          => 'pending',
            'type'            => 'regular_time',
            'source_leave_id' => $leave->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->delete(route('events.destroy', $event))
            ->assertRedirect()
            ->assertSessionHas('toast_errors');

        $this->assertDatabaseHas('events', [
            'id'              => $event->id,
            'source_leave_id' => $leave->id,
        ]);
    }

    /**
     * シナリオ 10c: Leave snapshot 由来の Event は画面上でも削除不可として表示する
     */
    public function test_absence_managed_event_delete_button_is_disabled_on_shift_assigner(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $leave = Leave::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
            'start_date'    => '2026-05-31',
            'status'        => 'approved',
        ]);

        Event::factory()->create([
            'district_id'     => $district->id,
            'department_id'   => $department->id,
            'event_date'      => '2026-05-31',
            'status'          => 'pending',
            'type'            => 'regular_time',
            'school_name'     => 'Managed School',
            'source_leave_id' => $leave->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('calendar.edit', ['event_date' => '2026-05-31']))
            ->assertOk()
            ->assertSee('Managed School')
            ->assertSee('Managed by absence');
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
     * - EventPolicy::canManage が district_id / department_id の不一致を検出して welcome へリダイレクト
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
            ->assertRedirect(route('welcome'));

        // DB が更新されていないこと
        $this->assertDatabaseHas('events', [
            'id'     => $otherEvent->id,
            'status' => 'pending',
        ]);
    }

    /**
     * シナリオ 13: admin は別スコープの Event を削除できない
     *
     * - EventPolicy::canManage が district_id / department_id の不一致を検出して welcome へリダイレクト
     */
    public function test_admin_cannot_delete_event_outside_scope(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $otherEvent = $this->makeEventOutsideScope();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->delete(route('events.destroy', $otherEvent))
            ->assertRedirect(route('welcome'));

        // DB にレコードが残っていること
        $this->assertDatabaseHas('events', ['id' => $otherEvent->id]);
    }
}
