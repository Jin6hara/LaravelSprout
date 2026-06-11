<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Department;
use App\Models\District;
use App\Models\Event;
use App\Models\Leave;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Notifications\ApprovalRequestedNotification;
use App\Services\LeaveApply\LeaveApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ApprovalController / NotificationController の承認通知スコープ制御テスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [承認詳細: Generated Shift を伴う Leave 承認]
 *   1. super_admin は現在スコープ内の Leave 承認詳細で Generated Shift Confirmation を確認できる
 *   2. Generated Shift がある Leave を却下する場合、shift の扱いを選ばないと却下できない
 *
 * [通知送信先: 管理範囲ベース]
 *   3. Leave 申請時の承認通知は、申請者の district/department を管理範囲に持つ super_admin だけに送信される
 *
 * [承認操作: 現在選択中スコープベース]
 *   4. super_admin が複数の管理範囲を持っていても、現在選択中でない district/department の承認詳細は開けない
 *
 * [通知一覧・既読操作: 現在選択中スコープベース]
 *   5. 通知レコード自体を持っていても、現在選択中スコープ外の承認通知は一覧に表示しない
 *   6. 「すべて既読」は現在選択中スコープで表示される通知だけを既読化する
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * テストのセットアップ方針
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - RefreshDatabase でテストごとに DB をリセット
 * - TestCase::setUp() で RoleSeeder が実行済み（admin/super_admin/general ロール作成）
 * - Leave / ApprovalRequest は district_id / department_id を持つ approvable からスコープを解決する
 * - super_admin の担当範囲は UserManagementScope で表現する
 * - CurrentScopeService は session('selected_scope_id') で現在スコープを解決するため、
 *   現在スコープを検証する HTTP リクエストでは withSession(['selected_scope_id' => $scope->id]) を付与する
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 重要な仕様メモ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - 通知送信時は「担当できるスコープ」に送る。
 *   ただし通知一覧・未読件数・一括既読・承認操作は「現在選択中スコープ」に限定する。
 *
 * - AuthorizationException は app/Exceptions/Handler.php で welcome へリダイレクトされる。
 *   そのため HTTP レスポンスは 403 ではなく 302 になる。
 *   Policy の判定そのものは $user->can(...) で false を確認する。
 *
 * - Notification::fake() を使うテストは送信対象の確認に集中する。
 *   通知一覧・一括既読のテストは database notifications を直接作成し、既に通知を持っているが
 *   現在スコープ外なら表示・既読対象にならないことを確認する。
 */
class ApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // ヘルパー
    // =========================================================================

    /**
     * Generated Shift を伴う pending Leave と ApprovalRequest を生成する。
     *
     * Leave と Event は同じ district/department に置き、
     * ApprovalController::show() が GeneratedShiftDecisionService 経由で
     * shift 確認 UI を表示できる状態を作る。
     *
     * @return array{0: ApprovalRequest, 1: Leave, 2: Event}
     */
    private function makeLeaveApprovalWithGeneratedShift(): array
    {
        $district = District::factory()->create();
        $department = Department::factory()->create();

        $requester = User::factory()->general()->create([
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        $leave = Leave::factory()->create([
            'user_id' => $requester->id,
            'start_date' => '2026-06-13',
            'kind' => 'paid',
            'excused' => 'excused',
            'status' => 'pending',
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        $event = Event::factory()->create([
            'district_id' => $district->id,
            'department_id' => $department->id,
            'event_date' => '2026-06-13',
            'original_user_id' => $requester->id,
            'school_name' => 'Approval Generated School',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'Lesson' => 'ALP L1',
            'status' => 'pending',
            'type' => 'regular_time',
            'source_leave_id' => $leave->id,
        ]);

        $approval = ApprovalRequest::create([
            'approvable_type' => Leave::class,
            'approvable_id' => $leave->id,
            'title' => 'Paid Leave Request',
            'requested_by_id' => $requester->id,
            'current_state' => 'pending',
            'metadata' => [
                'kind' => 'paid',
                'date' => '2026-06-13',
                'target_user_id' => $requester->id,
            ],
        ]);

        return [$approval, $leave, $event];
    }

    /**
     * 指定ユーザーに ApprovalRequest と同じ district/department の管理範囲を付与する。
     *
     * super_admin が承認詳細を開くには、ロールだけでなく現在スコープも一致している必要がある。
     * このヘルパーは既存テストの正常系で Policy 条件を満たすために使う。
     */
    private function giveApprovalScope(User $user, ApprovalRequest $approval): void
    {
        UserManagementScope::factory()->create([
            'user_id' => $user->id,
            'district_id' => $approval->approvalDistrictId(),
            'department_id' => $approval->approvalDepartmentId(),
        ]);
    }

    /**
     * 任意の district/department に属する Leave ApprovalRequest を作る。
     *
     * 現在スコープの切り替えテストで、関東案件・関西案件のように
     * 明示的に所属スコープが異なる承認案件を用意するために使う。
     */
    private function makeLeaveApprovalForScope(District $district, Department $department, string $title): ApprovalRequest
    {
        $requester = User::factory()->general()->create([
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        $leave = Leave::factory()->create([
            'user_id' => $requester->id,
            'status' => 'pending',
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        return ApprovalRequest::create([
            'approvable_type' => Leave::class,
            'approvable_id' => $leave->id,
            'title' => $title,
            'requested_by_id' => $requester->id,
            'current_state' => 'pending',
            'metadata' => [
                'kind' => 'paid',
                'target_user_id' => $requester->id,
            ],
        ]);
    }

    /**
     * database channel の通知レコードを直接作成する。
     *
     * Notification::fake() では notifications テーブルに保存されないため、
     * 通知一覧・一括既読のテストでは実際の DatabaseNotification を作る。
     * data.approval_request_id は ScopedNotificationService が承認案件スコープを解決するために必要。
     */
    private function createDatabaseNotification(User $user, ApprovalRequest $approval): DatabaseNotification
    {
        $id = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $id,
            'type' => ApprovalRequestedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => $approval->title,
                'approval_request_id' => $approval->id,
                'url' => route('approvals.show', $approval),
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DatabaseNotification::findOrFail($id);
    }

    // =========================================================================
    // 1〜2. 承認詳細: Generated Shift を伴う Leave 承認
    // =========================================================================

    /**
     * シナリオ 1:
     * super_admin は現在スコープ内の Leave 承認詳細を表示でき、
     * Generated Shift Confirmation と対象 shift 情報を確認できる。
     */
    public function test_super_admin_can_confirm_generated_shifts_before_denying_leave_request(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        [$approval] = $this->makeLeaveApprovalWithGeneratedShift();
        $this->giveApprovalScope($superAdmin, $approval);

        $this->actingAs($superAdmin)
            ->get(route('approvals.show', $approval))
            ->assertOk()
            ->assertSee('Generated Shift Confirmation')
            ->assertSee('Keep Shift')
            ->assertSee('Delete Shift')
            ->assertSee('generated_shift_action')
            ->assertSee('Approval Generated School')
            ->assertSee('ALP L1');
    }

    /**
     * シナリオ 2:
     * Generated Shift がある Leave を却下する場合、
     * generated_shift_action を選ばないリクエストは業務ルールで止められる。
     *
     * 期待結果:
     * - toast_errors がセッションに入る
     * - ApprovalRequest / Leave / Event は pending のまま残る
     */
    public function test_super_admin_must_confirm_generated_shift_action_before_denying_leave_request(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        [$approval, $leave, $event] = $this->makeLeaveApprovalWithGeneratedShift();
        $this->giveApprovalScope($superAdmin, $approval);

        $this->actingAs($superAdmin)
            ->post(route('approvals.deny', $approval), [
                'comment' => 'Not approved.',
            ])
            ->assertRedirect()
            ->assertSessionHas('toast_errors');

        $this->assertDatabaseHas('approval_requests', [
            'id' => $approval->id,
            'current_state' => 'pending',
        ]);
        $this->assertDatabaseHas('leaves', [
            'id' => $leave->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'source_leave_id' => $leave->id,
        ]);
    }

    // =========================================================================
    // 3. 通知送信先: 管理範囲ベース
    // =========================================================================

    /**
     * シナリオ 3:
     * Leave 申請時の通知送信先は、申請者の district/department と一致する
     * UserManagementScope を持つ super_admin のみに限定する。
     *
     * ここでは Notification::fake() で database/mail 送信を止め、
     * matchingApprover にだけ ApprovalRequestedNotification が送られることを確認する。
     */
    public function test_leave_approval_notification_is_sent_only_to_matching_scope_super_admins(): void
    {
        Notification::fake();

        $district = District::factory()->create();
        $department = Department::factory()->create();
        $otherDistrict = District::factory()->create();

        $requester = User::factory()->general()->create([
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        $matchingApprover = User::factory()->superAdmin()->create();
        UserManagementScope::factory()->create([
            'user_id' => $matchingApprover->id,
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        $otherApprover = User::factory()->superAdmin()->create();
        UserManagementScope::factory()->create([
            'user_id' => $otherApprover->id,
            'district_id' => $otherDistrict->id,
            'department_id' => $department->id,
        ]);

        app(LeaveApplicationService::class)->apply(
            $requester->id,
            ['2026-06-13'],
            'test',
            $requester->id
        );

        Notification::assertSentTo($matchingApprover, ApprovalRequestedNotification::class);
        Notification::assertNotSentTo($otherApprover, ApprovalRequestedNotification::class);
    }

    // =========================================================================
    // 4. 承認操作: 現在選択中スコープベース
    // =========================================================================

    /**
     * シナリオ 4:
     * super_admin が関東・関西の両方を担当できても、現在選択中スコープが関西なら
     * 関東の承認詳細は開けない。
     *
     * 期待結果:
     * - selected_scope_id = 関東: 承認詳細を表示できる
     * - selected_scope_id = 関西: Policy は false
     * - HTTP では Handler の仕様により 403 ではなく welcome へリダイレクト
     */
    public function test_super_admin_can_act_only_on_currently_selected_scope(): void
    {
        $department = Department::factory()->create();
        $kanto = District::factory()->create();
        $kansai = District::factory()->create();

        $superAdmin = User::factory()->superAdmin()->create();
        $kantoScope = UserManagementScope::factory()->create([
            'user_id' => $superAdmin->id,
            'district_id' => $kanto->id,
            'department_id' => $department->id,
        ]);
        $kansaiScope = UserManagementScope::factory()->create([
            'user_id' => $superAdmin->id,
            'district_id' => $kansai->id,
            'department_id' => $department->id,
        ]);

        $kantoApproval = $this->makeLeaveApprovalForScope($kanto, $department, 'Kanto Request');

        $this->withSession(['selected_scope_id' => $kantoScope->id])
            ->actingAs($superAdmin)
            ->get(route('approvals.show', $kantoApproval))
            ->assertOk();

        session(['selected_scope_id' => $kansaiScope->id]);
        $this->assertFalse($superAdmin->can('view', $kantoApproval));

        $this->withSession(['selected_scope_id' => $kansaiScope->id])
            ->actingAs($superAdmin)
            ->get(route('approvals.show', $kantoApproval))
            ->assertRedirect(route('welcome'))
            ->assertSessionHas('status', '403 This action is unauthorized.');
    }

    // =========================================================================
    // 5〜6. 通知一覧・既読操作: 現在選択中スコープベース
    // =========================================================================

    /**
     * シナリオ 5:
     * super_admin が関東・関西両方の通知レコードを持っていても、
     * 通知一覧には現在選択中スコープの承認通知だけを表示する。
     *
     * このテストでは selected_scope_id = 関東にして、
     * Kanto Request は表示され、Kansai Request は表示されないことを確認する。
     */
    public function test_notifications_index_shows_only_currently_selected_scope_approval_requests(): void
    {
        $department = Department::factory()->create();
        $kanto = District::factory()->create();
        $kansai = District::factory()->create();

        $superAdmin = User::factory()->superAdmin()->create();
        $kantoScope = UserManagementScope::factory()->create([
            'user_id' => $superAdmin->id,
            'district_id' => $kanto->id,
            'department_id' => $department->id,
        ]);
        UserManagementScope::factory()->create([
            'user_id' => $superAdmin->id,
            'district_id' => $kansai->id,
            'department_id' => $department->id,
        ]);

        $kantoApproval = $this->makeLeaveApprovalForScope($kanto, $department, 'Kanto Request');
        $kansaiApproval = $this->makeLeaveApprovalForScope($kansai, $department, 'Kansai Request');
        $this->createDatabaseNotification($superAdmin, $kantoApproval);
        $this->createDatabaseNotification($superAdmin, $kansaiApproval);

        $this->withSession(['selected_scope_id' => $kantoScope->id])
            ->actingAs($superAdmin)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Kanto Request')
            ->assertDontSee('Kansai Request');
    }

    /**
     * シナリオ 6:
     * 「すべて既読」はログインユーザーの全通知ではなく、
     * 現在選択中スコープで表示対象になる未読通知だけを既読化する。
     *
     * 期待結果:
     * - 関東スコープ選択中: 関東通知は read_at が入る
     * - 関西通知は同じユーザー宛でも read_at は null のまま
     */
    public function test_read_all_marks_only_currently_selected_scope_notifications_as_read(): void
    {
        $department = Department::factory()->create();
        $kanto = District::factory()->create();
        $kansai = District::factory()->create();

        $superAdmin = User::factory()->superAdmin()->create();
        $kantoScope = UserManagementScope::factory()->create([
            'user_id' => $superAdmin->id,
            'district_id' => $kanto->id,
            'department_id' => $department->id,
        ]);
        UserManagementScope::factory()->create([
            'user_id' => $superAdmin->id,
            'district_id' => $kansai->id,
            'department_id' => $department->id,
        ]);

        $kantoNotification = $this->createDatabaseNotification(
            $superAdmin,
            $this->makeLeaveApprovalForScope($kanto, $department, 'Kanto Request')
        );
        $kansaiNotification = $this->createDatabaseNotification(
            $superAdmin,
            $this->makeLeaveApprovalForScope($kansai, $department, 'Kansai Request')
        );

        $this->withSession(['selected_scope_id' => $kantoScope->id])
            ->actingAs($superAdmin)
            ->post(route('notifications.readAll'))
            ->assertRedirect();

        $this->assertNotNull($kantoNotification->fresh()->read_at);
        $this->assertNull($kansaiNotification->fresh()->read_at);
    }
}
