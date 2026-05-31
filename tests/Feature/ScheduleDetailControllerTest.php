<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\Lesson;
use App\Models\ScheduleDetail;
use App\Models\ScheduleLine;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ScheduleDetailController のテスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * [ゲスト / 権限]
 *   1. 未認証アクセスはログインページへリダイレクト
 *   2. general ユーザーは ScheduleDetail を追加できない
 *
 * [admin ユーザー（表示）]
 *   3. スコープ内の ScheduleLine の detail 編集画面を表示できる
 *   4. district は一致しても department が現在スコープと異なる場合は一覧へ戻る
 *
 * [admin ユーザー（作成 / 複製）]
 *   5. 既存 Lesson を使って空の ScheduleDetail を追加できる
 *   6. Lesson が存在しない場合は TEMP の placeholder Lesson を作成して追加できる
 *   7. 同一 slot の既存 detail がある場合、copy は日付をずらして複製する
 *
 * [admin ユーザー（一括更新）]
 *   8. lesson_code / ps_unique_lesson_code で Lesson を解決して一括更新できる
 *   9. 一括更新で一部 item が不正な場合、正常 item だけ保存し 207 を返す
 *
 * [スコープ分離]
 *  10. 別 district の ScheduleDetail は copy できない
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ポリシー仕様メモ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - ScheduleDetailPolicy::canManage → isAdmin() && detail.scheduleLine.district_id === currentDistrictId()
 *   ※ ScheduleDetail の個別操作は department_id を判定に含まない
 * - edit は ScheduleLinePolicy::view 後、Controller 内で department_id も確認する
 * - store / bulkUpdate は ScheduleLinePolicy::update で line の district scope を確認する
 * - bulkUpdate は item ごとに ScheduleDetailPolicy::update と LessonPolicy::update を確認する
 * - 権限エラーは Handler により welcome へリダイレクトされる
 */
class ScheduleDetailControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array{0: User, 1: UserManagementScope, 2: District, 3: Department}
     */
    private function makeAdmin(): array
    {
        $district = District::factory()->create();
        $department = Department::factory()->create();

        $admin = User::factory()->admin()->create([
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        $scope = UserManagementScope::factory()->create([
            'user_id' => $admin->id,
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);

        return [$admin, $scope, $district, $department];
    }

    private function makeLineInScope(District $district, Department $department): ScheduleLine
    {
        return ScheduleLine::factory()->create([
            'user_id' => null,
            'dow' => 1,
            'school_name' => 'In-Scope School',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'effective_start' => now()->subMonth()->toDateString(),
            'effective_end' => now()->addMonth()->toDateString(),
            'district_id' => $district->id,
            'department_id' => $department->id,
        ]);
    }

    private function makeLineOutsideScope(): ScheduleLine
    {
        return ScheduleLine::factory()->create([
            'user_id' => null,
            'district_id' => District::factory()->create()->id,
            'department_id' => Department::factory()->create()->id,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $line = ScheduleLine::factory()->create();

        $this->get(route('schedule_details.edit', $line))
            ->assertRedirect(route('login'));
    }

    /**
     * シナリオ 2: general ユーザーは ScheduleDetail を追加できない
     *
     * - route は auth のみだが、Controller の Policy で拒否される
     * - AuthorizationException は Handler により welcome へリダイレクトされる
     */
    public function test_general_user_cannot_store_detail(): void
    {
        $line = ScheduleLine::factory()->create();

        $this->actingAs(User::factory()->general()->create())
            ->postJson(route('schedule_details.store', $line))
            ->assertRedirect(route('welcome'));
    }

    /**
     * シナリオ 3: admin はスコープ内 ScheduleLine の detail 編集画面を表示できる
     *
     * - selected_scope_id の district / department と line が一致していること
     * - detailsEdit view に対象 line が渡ること
     */
    public function test_admin_can_view_details_for_line_in_scope(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $line = $this->makeLineInScope($district, $department);
        ScheduleDetail::factory()->create(['schedule_line_id' => $line->id]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('schedule_details.edit', $line))
            ->assertOk()
            ->assertViewIs('schedule.detailsEdit')
            ->assertViewHas('line', fn (ScheduleLine $viewLine) => $viewLine->is($line));
    }

    /**
     * シナリオ 4: department scope が変わった場合は detail 編集画面を表示せず一覧へ戻る
     *
     * - ScheduleLinePolicy は district のみ確認する
     * - Controller の追加チェックで department 不一致を検出する
     */
    public function test_admin_is_redirected_from_details_when_department_scope_changed(): void
    {
        [$admin, $scope, $district] = $this->makeAdmin();
        $otherDepartment = Department::factory()->create();
        $line = $this->makeLineInScope($district, $otherDepartment);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->get(route('schedule_details.edit', $line))
            ->assertRedirect('/schedule_manager');
    }

    /**
     * シナリオ 5: admin は既存 Lesson を使って空の ScheduleDetail を追加できる
     *
     * - start_time は 00:00:00
     * - effective_start は今日
     * - 既存の先頭 Lesson が紐づく
     */
    public function test_admin_can_store_blank_detail_with_existing_lesson(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $line = $this->makeLineInScope($district, $department);
        $lesson = Lesson::factory()->create(['lesson_code' => 'LS0001']);

        $response = $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('schedule_details.store', $line))
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['new_id']);

        $detailId = $response->json('new_id');

        $this->assertDatabaseHas('schedule_details', [
            'id' => $detailId,
            'schedule_line_id' => $line->id,
            'start_time' => '00:00:00',
            'lesson_id' => $lesson->id,
            'effective_start' => now()->toDateString(),
            'effective_end' => null,
        ]);
    }

    /**
     * シナリオ 6: Lesson が存在しない場合は placeholder Lesson を作成して detail を追加する
     *
     * - lesson_name は 未設定
     * - lesson_code は TEMP
     * - lesson_minute は 0
     */
    public function test_admin_store_blank_detail_creates_placeholder_lesson_when_no_lessons_exist(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $line = $this->makeLineInScope($district, $department);

        $response = $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('schedule_details.store', $line))
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['new_id']);

        $detail = ScheduleDetail::findOrFail($response->json('new_id'));

        $this->assertDatabaseHas('lessons', [
            'id' => $detail->lesson_id,
            'lesson_name' => '未設定',
            'lesson_code' => 'TEMP',
            'lesson_minute' => 0,
        ]);
    }

    /**
     * シナリオ 7: admin は ScheduleDetail を複製できる
     *
     * - 同じ line / start_time / lesson / effective_start が既にある場合は日付を進める
     * - effective_end も同じ delta 日数だけ進める
     */
    public function test_admin_can_copy_detail_and_shift_date_when_same_slot_exists(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $line = $this->makeLineInScope($district, $department);
        $lesson = Lesson::factory()->create(['lesson_code' => 'LSCOPY']);

        $source = ScheduleDetail::factory()->create([
            'schedule_line_id' => $line->id,
            'lesson_id' => $lesson->id,
            'start_time' => '10:00:00',
            'effective_start' => '2026-04-01',
            'effective_end' => '2026-04-30',
        ]);

        ScheduleDetail::factory()->create([
            'schedule_line_id' => $line->id,
            'lesson_id' => $lesson->id,
            'start_time' => '10:00:00',
            'effective_start' => '2026-04-02',
            'effective_end' => '2026-05-01',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('schedule_details.copy', $source))
            ->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['new_id']);

        $newId = $response->json('new_id');

        $this->assertDatabaseHas('schedule_details', [
            'id' => $newId,
            'schedule_line_id' => $line->id,
            'lesson_id' => $lesson->id,
            'start_time' => '10:00:00',
            'effective_start' => '2026-04-03',
            'effective_end' => '2026-05-02',
        ]);
    }

    /**
     * シナリオ 8: admin は ScheduleDetail を一括更新できる
     *
     * - ps_unique_lesson_code でも Lesson を解決できる
     * - schedule_details.note は detail_note で更新する
     * - lessons.note は note で更新する
     */
    public function test_admin_can_bulk_update_details(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $line = $this->makeLineInScope($district, $department);
        $oldLesson = Lesson::factory()->create(['lesson_code' => 'OLD01']);
        $newLesson = Lesson::factory()->create([
            'lesson_code' => 'NEW01',
            'ps_unique_lesson_code' => 'PSNEW01',
            'note' => 'old note',
        ]);
        $detail = ScheduleDetail::factory()->create([
            'schedule_line_id' => $line->id,
            'lesson_id' => $oldLesson->id,
            'note' => 'old detail note',
        ]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('schedule_details.bulk_update', $line), [
                'items' => [[
                    'id' => $detail->id,
                    'lesson_code' => 'PSNEW01',
                    'note' => 'updated lesson note',
                    'detail_note' => 'updated detail note',
                    'start_time' => '13:30',
                    'effective_start' => '2026-05-10',
                    'effective_end' => '2026-05-20',
                ]],
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'updated' => 1, 'errors' => []]);

        $this->assertDatabaseHas('schedule_details', [
            'id' => $detail->id,
            'lesson_id' => $newLesson->id,
            'start_time' => '13:30:00',
            'effective_start' => '2026-05-10',
            'effective_end' => '2026-05-20',
            'note' => 'updated detail note',
        ]);

        $this->assertDatabaseHas('lessons', [
            'id' => $newLesson->id,
            'note' => 'updated lesson note',
        ]);
    }

    /**
     * シナリオ 9: 一括更新で一部 item が不正な場合は 207 を返す
     *
     * - 正常 item は保存される
     * - 不正 item は errors に積まれる
     * - レスポンスは ok=false / updated=1
     */
    public function test_bulk_update_returns_partial_success_for_invalid_items(): void
    {
        [$admin, $scope, $district, $department] = $this->makeAdmin();
        $line = $this->makeLineInScope($district, $department);
        $oldLesson = Lesson::factory()->create(['lesson_code' => 'OLD02']);
        $newLesson = Lesson::factory()->create(['lesson_code' => 'NEW02']);
        $detail = ScheduleDetail::factory()->create([
            'schedule_line_id' => $line->id,
            'lesson_id' => $oldLesson->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('schedule_details.bulk_update', $line), [
                'items' => [
                    [
                        'id' => $detail->id,
                        'lesson_code' => 'NEW02',
                        'start_time' => '15:00',
                        'effective_start' => '2026-06-01',
                        'effective_end' => null,
                    ],
                    [
                        'id' => null,
                        'lesson_code' => 'NEW02',
                        'start_time' => '16:00',
                        'effective_start' => '2026-06-01',
                    ],
                    [
                        'id' => $detail->id,
                        'lesson_code' => 'MISSING',
                        'start_time' => '17:00',
                        'effective_start' => '2026-06-01',
                    ],
                ],
            ])
            ->assertStatus(207)
            ->assertJson([
                'ok' => false,
                'updated' => 1,
            ])
            ->assertJsonCount(2, 'errors');

        $this->assertDatabaseHas('schedule_details', [
            'id' => $detail->id,
            'lesson_id' => $newLesson->id,
            'start_time' => '15:00:00',
            'effective_start' => '2026-06-01',
            'effective_end' => null,
        ]);
    }

    /**
     * シナリオ 10: admin は別 district の ScheduleDetail を複製できない
     *
     * - ScheduleDetailPolicy が detail.scheduleLine の district 不一致を拒否する
     * - 新規 detail が作成されていないこと
     */
    public function test_admin_cannot_copy_detail_outside_scope(): void
    {
        [$admin, $scope] = $this->makeAdmin();
        $line = $this->makeLineOutsideScope();
        $detail = ScheduleDetail::factory()->create(['schedule_line_id' => $line->id]);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id])
            ->postJson(route('schedule_details.copy', $detail))
            ->assertRedirect(route('welcome'));

        $this->assertSame(1, ScheduleDetail::where('schedule_line_id', $line->id)->count());
    }
}
