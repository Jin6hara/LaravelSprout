<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\District;
use Illuminate\Database\Seeder;

class DistrictDepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Districts
        $districts = ['東日本', '西日本(阪)', '西日本(名)'];
        foreach ($districts as $name) {
            District::firstOrCreate(['name' => $name]);
        }

        // Departments
        $departments = ['Native', 'Bilingual'];
        foreach ($departments as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
    }
}
