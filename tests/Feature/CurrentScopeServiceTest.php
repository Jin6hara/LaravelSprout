<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\District;
use App\Models\User;
use App\Models\UserManagementScope;
use App\Services\CurrentScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CurrentScopeService のユニットテスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *   1. admin + 正しい selected_scope_id → 対応する district_id / department_id が返る
 *   2. admin + selected_scope_id = null + スコープなし → null が返る
 *   3. admin + 存在しない scope_id → null が返る
 *   4. general ユーザー → users.district_id / department_id が直接返る（スコープを使わない）
 *   5. 未認証 → null が返る
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * テストのセットアップ方針
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - RefreshDatabase でテストごとに DB をリセット
 * - CurrentScopeService はシングルトン登録されているが、
 *   TestCase::setUp() が毎回 createApplication() でアプリを再生成するため
 *   各テストメソッドで新しいインスタンスが得られる
 * - actingAs() + withSession() でスコープ解決の条件を再現する
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 重要な仕様メモ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - selected_scope_id = null かつスコープが存在する場合は最初のスコープを初期値として使う
 *   （フォールバック）。null を返すのはスコープが一件も存在しない場合のみ。
 * - general ユーザーはスコープを持たず、users.district_id / department_id を直接使う。
 * - Laravel アップグレード後もセッション解決ロジックが壊れていないかを確認するのが目的。
 */
class CurrentScopeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(District $district, Department $department): array
    {
        $admin = User::factory()->admin()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        $scope = UserManagementScope::factory()->create([
            'user_id'       => $admin->id,
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        return [$admin, $scope];
    }

    private function service(): CurrentScopeService
    {
        return app(CurrentScopeService::class);
    }

    // =========================================================================
    // 1. admin + 正しい selected_scope_id
    // =========================================================================

    /**
     * シナリオ 1: admin が正しい selected_scope_id をセッションに持つ場合、
     *             対応するスコープの district_id / department_id が返る
     *
     * - currentDistrictId() / currentDepartmentId() がスコープの値を返すことを確認する。
     * - アップグレード後もセッション → スコープ解決の流れが壊れていないことを検証する。
     */
    public function test_returns_scope_district_and_department_for_admin_with_valid_scope_id(): void
    {
        $district   = District::factory()->create();
        $department = Department::factory()->create();
        [$admin, $scope] = $this->makeAdmin($district, $department);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => $scope->id]);

        $service = $this->service();

        $this->assertEquals($district->id,   $service->currentDistrictId());
        $this->assertEquals($department->id, $service->currentDepartmentId());
    }

    // =========================================================================
    // 2. admin + selected_scope_id = null + スコープなし
    // =========================================================================

    /**
     * シナリオ 2: admin がスコープを持たず selected_scope_id も null の場合、null が返る
     *
     * - UserManagementScope が存在しないと ->first() が null を返す。
     * - currentDistrictId() / currentDepartmentId() はいずれも null になる。
     *
     * 補足: selected_scope_id = null かつスコープが存在する場合は
     *       最初のスコープをフォールバックとして使う（null は返らない）。
     */
    public function test_returns_null_when_admin_has_no_scopes(): void
    {
        $admin = User::factory()->admin()->create();

        // スコープを一件も作らない
        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => null]);

        $service = $this->service();

        $this->assertNull($service->currentDistrictId());
        $this->assertNull($service->currentDepartmentId());
    }

    // =========================================================================
    // 3. admin + 存在しない scope_id
    // =========================================================================

    /**
     * シナリオ 3: セッションの selected_scope_id が存在しない ID を指す場合、null が返る
     *
     * - ->where('id', $scopeId)->first() が null を返す。
     * - 別ユーザーのスコープ ID や削除済み ID を踏んでも null になることを確認する。
     */
    public function test_returns_null_when_scope_id_does_not_exist(): void
    {
        $district   = District::factory()->create();
        $department = Department::factory()->create();
        [$admin]    = $this->makeAdmin($district, $department);

        $this->actingAs($admin)
            ->withSession(['selected_scope_id' => 99999]); // 存在しない ID

        $service = $this->service();

        $this->assertNull($service->currentDistrictId());
        $this->assertNull($service->currentDepartmentId());
    }

    // =========================================================================
    // 4. general ユーザー
    // =========================================================================

    /**
     * シナリオ 4: general ユーザーは users.district_id / department_id が直接返る
     *
     * - general ユーザーはスコープを持たない。
     * - currentDistrictId() / currentDepartmentId() はユーザー自身の値を返す。
     * - セッションの selected_scope_id は無視される。
     */
    public function test_returns_user_district_and_department_for_general_user(): void
    {
        $district   = District::factory()->create();
        $department = Department::factory()->create();

        $general = User::factory()->create([
            'district_id'   => $district->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($general);

        $service = $this->service();

        $this->assertEquals($district->id,   $service->currentDistrictId());
        $this->assertEquals($department->id, $service->currentDepartmentId());
    }

    // =========================================================================
    // 5. 未認証
    // =========================================================================

    /**
     * シナリオ 5: 未認証ユーザーの場合、null が返る
     *
     * - auth()->user() が null を返すため、currentDistrictId() / currentDepartmentId() は null。
     */
    public function test_returns_null_for_unauthenticated_user(): void
    {
        $service = $this->service();

        $this->assertNull($service->currentDistrictId());
        $this->assertNull($service->currentDepartmentId());
    }
}
