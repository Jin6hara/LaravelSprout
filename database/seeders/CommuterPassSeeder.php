<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CommuterPass;

class CommuterPassSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- User 1 ----------
        CommuterPass::updateOrCreate(
            [
                'user_id'   => 1,
                'date_from' => '2025-10-15',
            ],
            [
                'date_to'       => '2025-11-14',
                'station_from'  => 'Ikoma',
                'station_to'    => 'Minamimorimachi',
                'cost'          => 6480, // 必要なら金額を入れる
                'note'          => '1ヶ月定期（Ikoma〜Minamimorimachi）',
            ]
        );

        // ---------- User 2 ----------
        CommuterPass::updateOrCreate(
            [
                'user_id'   => 2,
                'date_from' => '2025-10-03',
            ],
            [
                'date_to'       => '2025-11-02',
                'station_from'  => 'Higashi-Umeda',
                'station_to'    => 'Tennoji',
                'cost'          => 5980,
                'note'          => '1ヶ月定期（Higashi-Umeda〜Tennoji）',
            ]
        );

        // ---------- User 2 ----------
        CommuterPass::updateOrCreate(
            [
                'user_id'   => 2,
                'date_from' => '2025-11-03',
            ],
            [
                'date_to'       => '2025-12-02',
                'station_from'  => 'Higashi-Umeda',
                'station_to'    => 'Tennoji',
                'cost'          => 5980,
                'note'          => '1ヶ月定期（Higashi-Umeda〜Tennoji）',
            ]
        );
    }
}
