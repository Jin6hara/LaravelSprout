<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CompanyClosure;

class CompanyClosureSeeder extends Seeder
{
    public function run(): void
    {
        CompanyClosure::updateOrCreate(
            ['code' => 'GW', 'start_date' => '2025-04-29'],
            ['name' => 'Golden Week', 'end_date' => '2025-05-06', 'is_active' => true]
        );
        CompanyClosure::updateOrCreate(
            ['code' => 'SB', 'start_date' => '2025-08-05'],
            ['name' => 'Summer Break', 'end_date' => '2025-08-16', 'is_active' => true]
        );
        CompanyClosure::updateOrCreate(
            ['code' => 'WB', 'start_date' => '2025-12-24'],
            ['name' => 'Winter Break', 'end_date' => '2026-01-04', 'is_active' => true]
        );
        CompanyClosure::updateOrCreate(
            ['code' => 'CP', 'start_date' => '2025-12-07'],
            ['name' => 'Chrismas Party', 'end_date' => '2025-12-07', 'is_active' => true]
        );
    }
}
