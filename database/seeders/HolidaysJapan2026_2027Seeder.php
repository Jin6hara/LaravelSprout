<?php

// database/seeders/HolidaysJapan2026_2027Seeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Holiday;

class HolidaysJapan2026_2027Seeder extends Seeder
{
    public function run(): void
    {
        $source = 'https://www8.cao.go.jp/chosei/shukujitsu/gaiyou.html';

        $items = [
            // 2026 (令和8年)
            ['2026-01-01','元日',false],
            ['2026-01-12','成人の日',false],
            ['2026-02-11','建国記念の日',false],
            ['2026-02-23','天皇誕生日',false],
            ['2026-03-20','春分の日',false],
            ['2026-04-29','昭和の日',false],
            ['2026-05-03','憲法記念日',false],
            ['2026-05-04','みどりの日',false],
            ['2026-05-05','こどもの日',false],
            ['2026-05-06','休日',true],
            ['2026-07-20','海の日',false],
            ['2026-08-11','山の日',false],
            ['2026-09-21','敬老の日',false],
            ['2026-09-22','休日',true], // 国民の休日
            ['2026-09-23','秋分の日',false],
            ['2026-10-12','スポーツの日',false],
            ['2026-11-03','文化の日',false],
            ['2026-11-23','勤労感謝の日',false],

            // 2027 (令和9年)
            ['2027-01-01','元日',false],
            ['2027-01-11','成人の日',false],
            ['2027-02-11','建国記念の日',false],
            ['2027-02-23','天皇誕生日',false],
            ['2027-03-21','春分の日',false],
            ['2027-03-22','休日',true],
            ['2027-04-29','昭和の日',false],
            ['2027-05-03','憲法記念日',false],
            ['2027-05-04','みどりの日',false],
            ['2027-05-05','こどもの日',false],
            ['2027-07-19','海の日',false],
            ['2027-08-11','山の日',false],
            ['2027-09-20','敬老の日',false],
            ['2027-09-23','秋分の日',false],
            ['2027-10-11','スポーツの日',false],
            ['2027-11-03','文化の日',false],
            ['2027-11-23','勤労感謝の日',false],
        ];

        foreach ($items as [$d,$name,$observed]) {
            Holiday::updateOrCreate(
                ['date' => $d],
                [
                    'name' => $name,
                    'name_en' => null,
                    'country_code' => 'JP',
                    'year' => (int)substr($d,0,4),
                    'is_observed' => $observed,
                    'source' => $source,
                    'meta' => null,
                ]
            );
        }
    }
}
