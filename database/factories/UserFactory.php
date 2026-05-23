<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $familyName = fake()->lastName();
        $firstName  = fake()->firstName();

        return [
            'family_name'       => $familyName,
            'first_name'        => $firstName,
            'name'              => $familyName . ' ' . $firstName,
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
            'gender'            => fake()->randomElement(['male', 'female', 'other', 'unknown']),
            'employee_code'     => strtoupper(fake()->unique()->bothify('??####')),
            'district_id'       => null,
            'department_id'     => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * admin ロールを付与（PermissionRoleSeeder でロール作成済みが前提）
     */
    public function admin(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('admin');
        });
    }

    /**
     * super_admin ロールを付与
     */
    public function superAdmin(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('super_admin');
        });
    }

    /**
     * general ロールを付与
     */
    public function general(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->assignRole('general');
        });
    }
}
