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
        $districts = ['関東', '近畿CK', '中部'];
        foreach ($districts as $name) {
            District::firstOrCreate(['name' => $name]);
        }

        // Departments
        $departments = ['Native HR', 'Bilingual HR'];
        foreach ($departments as $name) {
            Department::firstOrCreate(['name' => $name]);
        }
    }
}
