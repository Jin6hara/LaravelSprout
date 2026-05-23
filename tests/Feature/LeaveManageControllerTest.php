<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\Event;
use App\Models\Leave;
use App\Models\ScheduleLine;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Services\Calendar\LeaveSnapshotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LeaveManageController のテスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [ゲスト]
 *   1. 未認証アクセスはログインページへリダイレクト
 *
 * [権限: general ユーザー]
 *   2. edit (leave_manager) を表示しようとすると welcome へリダイレクト
 *   3. store しようとすると welcome へリダイレクト
 *   4. update しようとすると welcome へリダイレクト
 *   5. destroy しようとすると welcome へリダイレクト
 *   6. bulkUpdate しようとすると welcome へリダイレクト
 *
 * [権限: admin ユーザー（正常系）]
 *   7. edit を表示できる → 200
 *   8. store でスコープ内に Leave が作成される → DB確認
 *   9. update でスコープ内の Leave を更新できる → DB確認
 *  10. destroy でスコープ内の Leave を削除できる → DB確認
 *  11. bulkUpdate で複数 Leave を一括更新できる → JSON + DB確認
 *
 * [スコープ分離]
 *  12. 別スコープの Leave は update できない → welcome へリダイレクト
 *  13. 別スコープの Leave は destroy できない → welcome へリダイレクト
 *
 * [Snapshot - LeaveSnapshotService 直接テスト]
 *  14. approved Leave + ScheduleLine → Event が生成される
 *  15. ScheduleLine が存在しない → Event は生成されない
 *  16. deleteSnapshotsForLeave → 既存 Event が削除される
 *  17. rejected Leave → Event は生成されない
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 注記: LeaveObserver は DB::afterCommit 経由でサービスを呼び出すため、
 * RefreshDatabase のトランザクション内では発火しない。
 * Snapshot 機能は LeaveSnapshotService を直接呼び出して検証する。
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LeaveManageControllerTest extends TestCase
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
     * admin のスコープ内にいる一般ユーザーを生成する。
     * LeavePolicy::canManageScopedLeave が targetUserIds() で検索するため、
     * district_id / department_id がスコープと一致している必要がある。
     */
    private function makeUserInScope(District $district, Department $department): User
    {
        return User::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);
    }

    /**
     * スコープ内の Leave を生成する。
     */
    private function makeLeaveInScope(District $district, Department $department, User $user): Leave
    {
        return Leave::factory()->create([
            'user_id'       => $user->id,
            'district_id'   => $district->id,
            'department_id' => $department->id,
            'start_date'    => now()->toDateString(),
            'kind'          => 'paid',
            'excused'       => 'excused',
            'status'        => 'approved',
        ]);
    }

    /**
     * 別スコープ（admin と無関係な district / department）の Leave を生成する。
     * スコープ分離テストで「操作できないはずのデータ」として使う。
     */
    private function makeLeaveOutsideScope(): Leave
    {
        $otherDistrict   = District::factory()->create();
        $otherDepartment = Department::factory()->create();

        $otherUser = User::factory()->create([
            'district_id'   => $otherDistrict->id,
            'department_id' => $otherDepartment->id,
        ]);

        return Leave::factory()->create([
            'user_id'       => $otherUser->id,
            'district_id'   => $otherDistrict->id,
            'department_id' => $otherDepartment->id,
            'start_date'    => now()->toDateString(),
            'kind'          => 'paid',
            'excused'       => 'excused',
            'status'        => 'approved',
        ]);
    }

    // =========================================================================
    // 1. ゲスト
    // =========================================================================

    /**
     * シナリオ 1: 未認証ユーザーは leave_manager にアクセスするとログインへリダイレクト
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('leaves.edit'))->assertRedirect(route('login'));
    }

    // =========================================================================
    // 2〜6. general ユーザー（全アクション welcome へリダイレクト）
    // =========================================================================

    /**
     * シナリオ 2: general ユーザーは leave_manager を表示できず welcome へリダイレクト
     */
    public function test_general_user_cannot_view_leave_manager(): void
    {
        $this->actingAs(User::factory()->general()->create())
            ->get(route('leaves.edit'))
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 3: general ユーザーは Leave を新規作成できず welcome へリダイレクト
     */
    public function test_general_user_cannot_store_leave(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)
            ->post(route('leaves.manage.store'), [
                'user_id'    => $user->id,
                'start_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 4: general ユーザーは Leave を更新できず welcome へリダイレクト
     */
    public function test_general_user_cannot_update_leave(): void
    {
        $user  = User::factory()->general()->create();
        $leave = Leave::factory()->create([
            'user_id'    => $user->id,
            'start_date' => now()->toDateString(),
            'kind'       => 'paid',
            'excused'    => 'excused',
            'status'     => 'approved',
        ]);

        $this->actingAs($user)
            ->put(route('leaves.update', $leave), [
                'user_id'    => $user->id,
                'start_date' => now()->toDateString(),
                'kind'       => 'paid',
                'excused'    => 'excused',
                'status'     => 'approved',
            ])
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 5: general ユーザーは Leave を削除できず welcome へリダイレクト
     */
    public function test_general_user_cannot_delete_leave(): void
    {
        $user  = User::factory()->general()->create();
        $leave = Leave::factory()->create([
            'user_id'    => $user->id,
            'start_date' => now()->toDateString(),
            'kind'       => 'paid',
            'excused'    => 'excused',
            'status'     => 'approved',
        ]);

        $this->actingAs($user)
            ->delete(route('leaves.destroy', $leave))
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 6: general ユーザーは Leave を一括更新できず welcome へリダイレクト
     */
    public function test_general_user_cannot_bulk_update_leave(): void
    {
        $user = User::factory()->general()->create();

        $this->actingAs($user)
            ->post(route('leaves.bulk_update'), ['items' => []])
            ->assertRedirect(route('welcome'));
    }

    // =========================================================================
    // 7. admin: edit（一覧表示）
    // =========================================================================

    /**
     * シナリオ 7: admin はスコープを選択した状態で leave_manager を表示できる
     */
    public function test_admin_can_view_leave_manager(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('leaves.edit'))
            ->assertOk();
    }

    // =========================================================================
    // 8. admin: store（新規作成）
    // =========================================================================

    /**
     * シナリオ 8: admin は Leave を新規作成できる
     *
     * - store は user_id = null / kind = special / status = approved で固定作成
     * - district_id / department_id はスコープから自動セット
     */
    public function test_admin_can_store_leave(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $date = now()->toDateString();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->post(route('leaves.manage.store'), [
                'user_id'    => $admin->id,
                'start_date' => $date,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leaves', [
            'start_date'    => $date,
            'kind'          => 'special',
            'status'        => 'approved',
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);
    }

    // =========================================================================
    // 9. admin: update（更新）
    // =========================================================================

    /**
     * シナリオ 9: admin はスコープ内の Leave を更新できる
     *
     * - LeavePolicy::update → canManageScopedLeave → leave.user_id が targetUserIds に含まれること
     * - UserPolicy::manage → isScopedAdminFor → 同スコープのユーザーであること
     */
    public function test_admin_can_update_leave_in_scope(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $targetUser = $this->makeUserInScope($district, $department);
        $leave      = $this->makeLeaveInScope($district, $department, $targetUser);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->put(route('leaves.update', $leave), [
                'user_id'    => $targetUser->id,
                'start_date' => now()->toDateString(),
                'kind'       => 'absence',
                'excused'    => 'unexcused',
                'status'     => 'approved',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('leaves', [
            'id'      => $leave->id,
            'kind'    => 'absence',
            'excused' => 'unexcused',
        ]);
    }

    // =========================================================================
    // 10. admin: destroy（削除）
    // =========================================================================

    /**
     * シナリオ 10: admin はスコープ内の Leave を削除できる
     *
     * - 削除後に DB にレコードが存在しないこと
     */
    public function test_admin_can_delete_leave_in_scope(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $targetUser = $this->makeUserInScope($district, $department);
        $leave      = $this->makeLeaveInScope($district, $department, $targetUser);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->delete(route('leaves.destroy', $leave))
            ->assertRedirect();

        $this->assertDatabaseMissing('leaves', ['id' => $leave->id]);
    }

    // =========================================================================
    // 11. admin: bulkUpdate（一括更新）
    // =========================================================================

    /**
     * シナリオ 11: admin は複数の Leave を一括更新できる
     *
     * - JSON レスポンスで ok=true / updated=1 が返ること
     * - DB の kind / status が更新されること
     */
    public function test_admin_can_bulk_update_leaves(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();

        $targetUser = $this->makeUserInScope($district, $department);
        $leave      = $this->makeLeaveInScope($district, $department, $targetUser);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('leaves.bulk_update'), [
                'items' => [[
                    'id'         => $leave->id,
                    'user_id'    => $targetUser->id,
                    'start_date' => now()->toDateString(),
                    'kind'       => 'special',
                    'excused'    => 'excused',
                    'status'     => 'pending',
                ]],
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'updated' => 1]);

        $this->assertDatabaseHas('leaves', [
            'id'     => $leave->id,
            'kind'   => 'special',
            'status' => 'pending',
        ]);
    }

    // =========================================================================
    // 12〜13. スコープ分離（別 district/department のデータは操作不可）
    // =========================================================================

    /**
     * シナリオ 12: admin は別スコープの Leave を更新できない
     *
     * - LeavePolicy::canManageScopedLeave が leave.user_id の不一致を検出して welcome へリダイレクト
     * - DB が更新されていないこと
     */
    public function test_admin_cannot_update_leave_outside_scope(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $otherLeave = $this->makeLeaveOutsideScope();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->put(route('leaves.update', $otherLeave), [
                'user_id'    => $otherLeave->user_id,
                'start_date' => now()->toDateString(),
                'kind'       => 'absence',
                'excused'    => 'unexcused',
                'status'     => 'approved',
            ])
            ->assertRedirect(route('welcome'));

        $this->assertDatabaseHas('leaves', [
            'id'   => $otherLeave->id,
            'kind' => 'paid',
        ]);
    }

    /**
     * シナリオ 13: admin は別スコープの Leave を削除できない
     *
     * - LeavePolicy::canManageScopedLeave が leave.user_id の不一致を検出して welcome へリダイレクト
     * - DB にレコードが残っていること
     */
    public function test_admin_cannot_delete_leave_outside_scope(): void
    {
        [$admin, $scope] = $this->makeAdmin();

        $otherLeave = $this->makeLeaveOutsideScope();

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->delete(route('leaves.destroy', $otherLeave))
            ->assertRedirect(route('welcome'));

        $this->assertDatabaseHas('leaves', ['id' => $otherLeave->id]);
    }

    // =========================================================================
    // 14〜17. Snapshot (LeaveSnapshotService 直接テスト)
    //
    // LeaveObserver は DB::afterCommit 経由でサービスを呼ぶため、
    // RefreshDatabase のトランザクション内では発火しない。
    // サービスを直接呼び出してスナップショット生成ロジックを検証する。
    // =========================================================================

    /**
     * シナリオ 14: approved Leave に対して ScheduleLine が存在する場合、
     * rebuildSnapshotsForLeave で source_leave_id 付きの Event が生成される
     */
    public function test_snapshot_creates_events_for_approved_leave_with_schedule_line(): void
    {
        $district   = District::factory()->create();
        $department = Department::factory()->create();

        $user = User::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        // 特定の曜日に合わせた日付を選び、ScheduleLine を用意
        $date = Carbon::parse('next Monday');
        $dow  = $date->dayOfWeek; // 1 (月曜)

        $line = ScheduleLine::factory()->create([
            'user_id'         => $user->id,
            'dow'             => $dow,
            'effective_start' => $date->copy()->subWeek()->toDateString(),
            'effective_end'   => $date->copy()->addWeek()->toDateString(),
            'start_time'      => '09:00:00',
            'end_time'        => '17:00:00',
            'school_name'     => 'Test School',
            'district_id'     => $district->id,
            'department_id'   => $department->id,
        ]);

        $leave = Leave::factory()->create([
            'user_id'       => $user->id,
            'start_date'    => $date->toDateString(),
            'status'        => 'approved',
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        app(LeaveSnapshotService::class)->rebuildSnapshotsForLeave($leave);

        $this->assertDatabaseHas('events', [
            'source_leave_id'         => $leave->id,
            'source_schedule_line_id' => $line->id,
            'event_date'              => $date->toDateString(),
        ]);
    }

    /**
     * シナリオ 15: ScheduleLine が存在しない場合、Event は生成されない
     */
    public function test_snapshot_creates_no_events_when_no_schedule_lines(): void
    {
        $district   = District::factory()->create();
        $department = Department::factory()->create();

        $user = User::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        $leave = Leave::factory()->create([
            'user_id'       => $user->id,
            'start_date'    => now()->toDateString(),
            'status'        => 'approved',
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        app(LeaveSnapshotService::class)->rebuildSnapshotsForLeave($leave);

        $this->assertDatabaseMissing('events', ['source_leave_id' => $leave->id]);
    }

    /**
     * シナリオ 16: deleteSnapshotsForLeave を呼ぶと source_leave_id 付きの Event が削除される
     */
    public function test_snapshot_deletes_events_when_delete_is_called(): void
    {
        $district   = District::factory()->create();
        $department = Department::factory()->create();

        $user = User::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        $date = Carbon::parse('next Monday');
        $dow  = $date->dayOfWeek;

        ScheduleLine::factory()->create([
            'user_id'         => $user->id,
            'dow'             => $dow,
            'effective_start' => $date->copy()->subWeek()->toDateString(),
            'effective_end'   => $date->copy()->addWeek()->toDateString(),
            'district_id'     => $district->id,
            'department_id'   => $department->id,
        ]);

        $leave = Leave::factory()->create([
            'user_id'       => $user->id,
            'start_date'    => $date->toDateString(),
            'status'        => 'approved',
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        // まずスナップショットを生成
        app(LeaveSnapshotService::class)->rebuildSnapshotsForLeave($leave);
        $this->assertDatabaseHas('events', ['source_leave_id' => $leave->id]);

        // 削除の検証
        app(LeaveSnapshotService::class)->deleteSnapshotsForLeave($leave);

        $this->assertDatabaseMissing('events', ['source_leave_id' => $leave->id]);
    }

    /**
     * シナリオ 17: rejected Leave に対してはスナップショットが生成されない
     *
     * - rebuildSnapshotsForLeave は approved / pending のみ対象
     */
    public function test_snapshot_does_not_create_events_for_rejected_leave(): void
    {
        $district   = District::factory()->create();
        $department = Department::factory()->create();

        $user = User::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        $date = Carbon::parse('next Monday');
        $dow  = $date->dayOfWeek;

        ScheduleLine::factory()->create([
            'user_id'         => $user->id,
            'dow'             => $dow,
            'effective_start' => $date->copy()->subWeek()->toDateString(),
            'effective_end'   => $date->copy()->addWeek()->toDateString(),
            'district_id'     => $district->id,
            'department_id'   => $department->id,
        ]);

        $leave = Leave::factory()->create([
            'user_id'       => $user->id,
            'start_date'    => $date->toDateString(),
            'status'        => 'rejected',
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        app(LeaveSnapshotService::class)->rebuildSnapshotsForLeave($leave);

        $this->assertDatabaseMissing('events', ['source_leave_id' => $leave->id]);
    }
}
