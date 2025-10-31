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
        // 1=>A, 2=>B, ... 19=>S
        $y = array_combine(range(1, 19), range('A', 'S'));

        for ($i = 1; $i <= 19; $i++) {

            if ($i >= 1 && $i <= 4) {
                $gender = "other";
            } else if ($i >= 7 && $i <= 10) {
                $gender = "female";
            } else if ($i === 5 || $i === 6) {
                $gender = "male";
            } else {
                $gender = "unknown";
            }

            $timestamp = now()->addMinutes(10 * $i);

            User::create([
                'family_name' => "{$i}hara",
                'first_name' => "{$y[$i]}im",
                'email' => "job{$i}hara@gmail.com",
                'password' => Hash::make('laravel'),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'gender' => $gender,
                'profile_picture' => null,
                'note' => "{$y[$i]}im is has been in Japan for more than " . ($i + 1) . " years.",
                // 🔽 追加項目
                'employee_code' => str_pad($i, 6, '0', STR_PAD_LEFT), // 000001〜、6桁
                'address' => "大阪府大阪市テスト区ララベル{$i}丁目",
                'phone_number' => str_pad("0901234" . $i, 11, '0', STR_PAD_RIGHT) //11桁
            ]);
        }
    }
}
