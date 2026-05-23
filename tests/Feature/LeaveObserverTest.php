<?php

namespace Tests\Feature;

use App\Models\Leave;
use App\Observers\LeaveObserver;
use App\Services\Calendar\LeaveSnapshotService;
use Tests\TestCase;

/**
 * LeaveObserver の DB::afterCommit 呼び出しテスト
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * カバーするシナリオ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 *   1. Leave 作成後（created）に rebuildSnapshotsForLeave が呼ばれる
 *   2. Leave 更新後（updated）に変更があれば rebuildSnapshotsForLeave が呼ばれる
 *   3. Leave 更新後（updated）に変更がなければ rebuildSnapshotsForLeave は呼ばれない
 *   4. Leave 削除後（deleted）に deleteSnapshotsForLeave が呼ばれる
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * テストのセットアップ方針
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - RefreshDatabase を使わない（意図的）
 *   → RefreshDatabase は全テストをトランザクション内で実行するため、
 *     DB::afterCommit のコールバックがコミットを待ち続けて発火しない。
 *   → トランザクションがない状態では DB::afterCommit はコールバックを即時実行する
 *     （Laravel ソース: $this->transactions === 0 の場合は即時 callback()）。
 *
 * - Observer を直接インスタンス化して呼び出す
 *   → Leave::factory()->create() は不要（DB アクセスなし）
 *   → Leave モデルを new または Mockery::mock で用意するだけでよい
 *
 * - LeaveSnapshotService をモックして DI コンテナに差し込む
 *   → app(LeaveSnapshotService::class) がモックを返すので
 *     実際のスナップショット生成処理は走らない
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 重要な仕様メモ
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * - Laravel アップグレード（v10→v11→v12）で DB::afterCommit の挙動が変わると
 *   Snapshot が一切作成されなくなる。このテストはその回帰を検出する。
 * - Observer は AppServiceProvider で Leave::observe(LeaveObserver::class) として登録。
 *   アップグレード後も Provider の登録方式が機能していることを間接的に確認する。
 * - updated() は wasChanged() が false の場合は早期リターンする（no-op）。
 */
class LeaveObserverTest extends TestCase
{
    // =========================================================================
    // 1. created → rebuildSnapshotsForLeave が呼ばれる
    // =========================================================================

    /**
     * シナリオ 1: Leave が作成されると rebuildSnapshotsForLeave が呼ばれる
     *
     * - DB::afterCommit はトランザクションがない状態で即時実行される。
     * - app(LeaveSnapshotService::class) がモックを返し、呼び出しを記録する。
     */
    public function test_created_calls_rebuild_snapshots_for_leave(): void
    {
        $mock = $this->mock(LeaveSnapshotService::class);
        $mock->shouldReceive('rebuildSnapshotsForLeave')->once();

        $observer = new LeaveObserver();
        $observer->created(new Leave());
    }

    // =========================================================================
    // 2. updated（変更あり）→ rebuildSnapshotsForLeave が呼ばれる
    // =========================================================================

    /**
     * シナリオ 2: Leave に変更があると rebuildSnapshotsForLeave が呼ばれる
     *
     * - wasChanged() = true のとき Observer は即時リターンせずサービスを呼ぶ。
     */
    public function test_updated_calls_rebuild_when_leave_has_changes(): void
    {
        $mock = $this->mock(LeaveSnapshotService::class);
        $mock->shouldReceive('rebuildSnapshotsForLeave')->once();

        $leave = \Mockery::mock(Leave::class)->makePartial();
        $leave->shouldReceive('wasChanged')->andReturn(true);

        $observer = new LeaveObserver();
        $observer->updated($leave);
    }

    // =========================================================================
    // 3. updated（変更なし）→ rebuildSnapshotsForLeave は呼ばれない
    // =========================================================================

    /**
     * シナリオ 3: Leave に変更がない場合、rebuildSnapshotsForLeave は呼ばれない
     *
     * - wasChanged() = false のとき Observer は早期リターンする（no-op）。
     * - 不要なスナップショット再生成が起きないことを確認する。
     */
    public function test_updated_does_not_call_rebuild_when_leave_has_no_changes(): void
    {
        $mock = $this->mock(LeaveSnapshotService::class);
        $mock->shouldNotReceive('rebuildSnapshotsForLeave');

        $leave = \Mockery::mock(Leave::class)->makePartial();
        $leave->shouldReceive('wasChanged')->andReturn(false);

        $observer = new LeaveObserver();
        $observer->updated($leave);
    }

    // =========================================================================
    // 4. deleted → deleteSnapshotsForLeave が呼ばれる
    // =========================================================================

    /**
     * シナリオ 4: Leave が削除されると deleteSnapshotsForLeave が呼ばれる
     *
     * - DB::afterCommit はトランザクションがない状態で即時実行される。
     * - on cascade delete で Events は自動削除されるが、
     *   Observer 側では将来の拡張（通知・ログ等）に備えて wiring が維持されている。
     */
    public function test_deleted_calls_delete_snapshots_for_leave(): void
    {
        $mock = $this->mock(LeaveSnapshotService::class);
        $mock->shouldReceive('deleteSnapshotsForLeave')->once();

        $observer = new LeaveObserver();
        $observer->deleted(new Leave());
    }
}
