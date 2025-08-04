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
        for ($i = 1; $i <= 19; $i++) {

            if ($i >= 1 && $i <= 4) {
                $gender = "other";
                $role = "general";
            } else if ($i >= 7 && $i <= 10) {
                $gender = "female";
                $role = "general";
            } else if ($i === 5 || $i === 6) {
                $gender = "male";
                $role = "admin";
            } else {
                $gender = "unknown";
                $role = "general";
            }

            $timestamp = now()->addMinutes(10 * $i);

            User::create([
                'name' => "{$i}hara",
                'email' => "test{$i}@test.com",
                'password' => Hash::make('laravel'),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'gender' => $gender,
                'role' => $role,
                'profile_picture' => "default_{$gender}.png",
                'self_introduction' => "私の名前は{$i}haraです。",

                // 🔽 追加項目
                'employee_code' => str_pad($i, 5, '0', STR_PAD_LEFT), // 00001〜、5桁
                'address' => "大阪府大阪市テスト区ララベル{$i}丁目",
                'phone_number' => str_pad("0901234" . $i, 11, '0', STR_PAD_RIGHT)//11桁

            ]);
        }
    }
}
