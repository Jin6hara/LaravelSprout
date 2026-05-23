<?php

namespace Tests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie Permission のキャッシュをリセット（RefreshDatabase 後に必須）
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 全 Feature Test で admin / super_admin / general ロールを使えるようにする
        $this->seed(RoleSeeder::class);
    }
}
