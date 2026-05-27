<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class PermissionRoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1) 必要ロールを作成（guard_name は web）
        //将来「Seederでロールに権限を付与する」予定がある為、変数を定義
        $super   = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $admin   = Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        $general = Role::firstOrCreate(['name' => 'general',     'guard_name' => 'web']);
        $trainer = Role::firstOrCreate(['name' => 'trainer',     'guard_name' => 'web']);

        $scheduleViewAll = Permission::firstOrCreate(['name' => 'schedule.viewAll', 'guard_name' => 'web']);
        $teacherViewAll = Permission::firstOrCreate(['name' => 'teacher.viewAll', 'guard_name' => 'web']);

        $super->givePermissionTo($scheduleViewAll);
        $admin->givePermissionTo($scheduleViewAll);
        $trainer->givePermissionTo($scheduleViewAll);

        $super->givePermissionTo($teacherViewAll);
        $admin->givePermissionTo($teacherViewAll);
        $trainer->givePermissionTo($teacherViewAll);

        // 2) ユーザーID → ロールの割当マップ
        $explicit = [
            6 => 'super_admin',
            5 => 'admin',
            4 => 'trainer',
        ];

        // 3) ID 1〜19 を走査し、明示指定以外は general を付与
        foreach (range(1, 19) as $id) {
            $user = User::find($id);
            if (!$user) {
                // UsersSeeder より先に実行される等で存在しない場合はスキップ
                continue;
            }

            $roleName = $explicit[$id] ?? 'general';

            // 既存ロールを置き換え（安全に同期）
            $user->syncRoles([$roleName]);
        }
    }
}
