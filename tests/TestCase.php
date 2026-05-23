<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Vite;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Vite マニフェストが存在しないテスト環境での例外を回避
        Vite::fake();

        // Spatie Permission のキャッシュをリセット（RefreshDatabase 後に必須）
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 全 Feature Test で admin / super_admin / general ロールを使えるようにする
        $this->seed(RoleSeeder::class);
    }
}
