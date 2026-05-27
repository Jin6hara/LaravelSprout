<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * テスト用：ロールのみ作成（ユーザーへの割当はしない）
 * Feature Test の setUp で $this->seed(RoleSeeder::class) として使用する
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'admin', 'general', 'trainer'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
