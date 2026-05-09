<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\District;
use App\Models\School;
use App\Models\User;
use App\Models\UserManagementScope;
use Illuminate\Database\Seeder;

class ManagementScopeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * 前提: DistrictDepartmentSeeder / UsersSeeder / PermissionRoleSeeder / SchoolSeeder 実行済み
     *
     * 処理:
     * 1. Users 1〜19 に district_id / department_id を割り当てる
     * 2. super_admin (id=6) に全 district × department の user_management_scopes を付与
     * 3. admin (id=5) に 近畿CK の 2 件を付与
     * 4. 全 School を 近畿CK に割り当てる（既存 SchoolSeeder データはすべて大阪・京都・神戸エリア）
     */
    public function run(): void
    {
        $kanto     = District::where('name', '関東')->first();
        $kinki     = District::where('name', '近畿CK')->first();
        $chubu     = District::where('name', '中部')->first();
        $native    = Department::where('name', 'Native HR')->first();
        $bilingual = Department::where('name', 'Bilingual HR')->first();

        // 1) ユーザーへの district / department 割り当て
        // id => [district, department]
        $userAssignments = [
            1  => [$kinki, $native],
            2  => [$kinki, $native],
            3  => [$kinki, $native],
            4  => [$kinki, $native],
            5  => [$kinki, $native],    // admin
            6  => [$kinki, $native],    // super_admin
            7  => [$kinki, $native],
            8  => [$kinki, $native],
            9  => [$kinki, $native],
            10 => [$kinki, $native],
            11 => [$kinki, $native],
            12 => [$kinki, $native],
            13 => [$kinki, $native],
            14 => [$kinki, $native],
            15 => [$kinki, $native],
            16 => [$kanto, $native],
            17 => [$kanto, $native],
            18 => [$kanto, $native],
            19 => [$kanto, $native],
        ];

        foreach ($userAssignments as $userId => [$district, $dept]) {
            User::where('id', $userId)->update([
                'district_id'   => $district->id,
                'department_id' => $dept->id,
            ]);
        }

        // 2) super_admin (id=6): 全 district × department の組み合わせ (6件)
        $superAdmin    = User::find(6);
        $allDistricts  = District::all();
        $allDepartments = Department::all();

        foreach ($allDistricts as $d) {
            foreach ($allDepartments as $dep) {
                UserManagementScope::firstOrCreate([
                    'user_id'       => $superAdmin->id,
                    'district_id'   => $d->id,
                    'department_id' => $dep->id,
                ]);
            }
        }

        // 3) admin (id=5): 近畿CK の 2 件のみ
        $admin = User::find(5);

        foreach ($allDepartments as $dep) {
            UserManagementScope::firstOrCreate([
                'user_id'       => $admin->id,
                'district_id'   => $kinki->id,
                'department_id' => $dep->id,
            ]);
        }

        // 4) 全 School を 近畿CK に割り当て
        // SchoolSeeder のデータはすべて大阪・京都・神戸エリア（近畿CK）
        School::query()->update(['district_id' => $kinki->id]);
    }
}
