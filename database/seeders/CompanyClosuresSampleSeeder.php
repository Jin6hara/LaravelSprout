<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CompanyClosure;

class CompanyClosuresSampleSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            // 例: 会社の「法定休日」扱い（毎週日曜をDBで持たないなら、アプリ側businessHoursで表現でもOK）
            // 固定日サンプル: 年末年始の全社休（会社規定に合わせて調整）
            ['2025-06-01', '全社休業日', 'company_off', true],
            ['2025-08-04', '全社休業日', 'company_off', true],
            ['2026-06-07', '全社休業日', 'company_off', true],
            ['2026-08-03', '全社休業日', 'company_off', true],
        ];

        foreach ($samples as [$d,$name,$type,$full]) {
            CompanyClosure::updateOrCreate(
                ['date' => $d, 'type' => $type],
                ['name' => $name, 'is_full_day' => $full, 'meta' => null]
            );
        }
    }
}
