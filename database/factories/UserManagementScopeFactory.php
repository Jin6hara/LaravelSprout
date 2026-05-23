<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\District;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserManagementScope>
 */
class UserManagementScopeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'       => User::factory(),
            'district_id'   => District::factory(),
            'department_id' => Department::factory(),
        ];
    }
}
