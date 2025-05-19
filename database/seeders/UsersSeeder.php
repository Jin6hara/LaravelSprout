<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
//use Carbon\Carbon;


class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($i = 1; $i <= 9; $i++) {

            if ($i === 1 || $i === 2) {
                $gender = "other";
                $role = "general";
            } else if ($i === 3 || $i === 4) {
                $gender = "female";
                $role = "general";
            } else if ($i === 5 || $i === 6) {
                $gender = "male";
                $role = "admin";
            } else {
                $gender = "unknown";
                $role = "general";
            }

            $timestamp = now()->addMinutes(10*$i);

            User::create([
                'name' => "{$i}hara",
                'email' => "test{$i}@test.com",
                'password' => Hash::make('laravel'),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'gender' => "{$gender}",
                'role' => "{$role}",
                'profile_picture' => "default_{$gender}.png",
                'self_introduction' => "私の名前は{$i}haraです。"
            ]);
        }
    }
}
