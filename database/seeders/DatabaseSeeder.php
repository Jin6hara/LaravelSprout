<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Leave;
use Illuminate\Database\Seeder;
use Database\Seeders\UsersSeeder;
use Database\Seeders\DistrictDepartmentSeeder;
use Database\Seeders\ManagementScopeSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DistrictDepartmentSeeder::class,
            UsersSeeder::class,
            EmploymentTermsSeeder::class,
            LeavePeriodsSeeder::class,
            PermissionRoleSeeder::class,
            HolidaysJapan2026_2027Seeder::class,
            RestPatternSeeder::class,
            CompanyClosureSeeder::class,
            RestPatternAdjustmentSeeder::class,
            ScheduleSeeder::class,
            LeaveSeeder::class,
            //EventSeeder::class, //LeaveSnapshotServiceで生成されるのでコメントアウト
            LessonSeeder::class,
            ScheduleDetailSeeder::class,
            ExpenseReportSeptemberSeeder::class,
            ExpenseSeptemberBlankSeeder::class,
            SchoolSeeder::class,
            ManagementScopeSeeder::class,
            CommuterPassSeeder::class,
            SubsTableSeeder::class,
            ScheduleDetailsCopySeeder::class,
        ]);

        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
