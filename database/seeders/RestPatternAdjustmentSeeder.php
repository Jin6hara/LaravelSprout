<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{RestPattern, RestPatternAdjustment};

class RestPatternAdjustmentSeeder extends Seeder
{
    public function run(): void
    {
        $monTue = RestPattern::where('code', 'MON_TUE')->first(); // 月火
        $thuFri = RestPattern::where('code', 'THU_FRI')->first(); // 木金
        if (!$monTue || !$thuFri) return;

        // 月火パターン：調整休日（ORD）
        RestPatternAdjustment::updateOrCreate(
            ['rest_pattern_id' => $monTue->id, 'date' => '2025-04-02'],
            ['kind' => 'add_off', 'code' => 'ORD', 'title' => '調整休日（ORD）', 'is_active' => true]
        );

        // 木金パターン：調整出勤（RWD）
        foreach (['2025-07-24', '2025-10-16', '2025-11-06'] as $d) {
            RestPatternAdjustment::updateOrCreate(
                ['rest_pattern_id' => $thuFri->id, 'date' => $d],
                ['kind' => 'work_instead', 'code' => 'RWD', 'title' => '調整出勤（RWD）', 'is_active' => true]
            );
        }
    }
}
