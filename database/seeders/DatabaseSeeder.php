<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\Leave;
use Illuminate\Database\Seeder;
use Database\Seeders\UsersSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UsersSeeder::class,
            EmploymentTermsSeeder::class,
            LeavePeriodsSeeder::class,
            PermissionRoleSeeder::class,
            HolidaysJapan2025_2026Seeder::class,
            RestPatternSeeder::class,
            CompanyClosureSeeder::class,
            RestPatternAdjustmentSeeder::class,
            ScheduleSeeder::class,
            LeaveSeeder::class,
            //EventSeeder::class, //LeaveSnapshotServiceで生成されるのでコメントアウト
            LessonStartTimeSeeder::class,
            LessonSeeder::class,
            ScheduleDetailSeeder::class,
            ExpenseReportSeptemberSeeder::class,
            ExpenseSeptemberBlankSeeder::class,
            SchoolSeeder::class,
            CommuterPassSeeder::class,
            SubsTableSeeder::class,
            RouteDeclarationUser2Seeder::class,
            ScheduleDetailsCopySeeder::class,
        ]);

        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
