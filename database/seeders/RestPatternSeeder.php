<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{RestPattern, RestPatternRule, UserRestPattern, User};
use Carbon\Carbon;

class RestPatternSeeder extends Seeder
{
    public function run(): void
    {
        // ① 月火休日（法定=月 / 所定=火）
        $monTue = RestPattern::firstOrCreate(['code' => 'MON_TUE'], ['name' => '月火休日']);
        $this->rules($monTue, [
            1 => 'statutory_off', // 月
            2 => 'prescribed_off', // 火
            0 => 'work',
            3 => 'work',
            4 => 'work',
            5 => 'work',
            6 => 'work',
        ]);

        // ② 木金休日（所定=木 / 法定=金）
        $thuFri = RestPattern::firstOrCreate(['code' => 'THU_FRI'], ['name' => '木金休日']);
        $this->rules($thuFri, [
            4 => 'prescribed_off', // 木
            5 => 'statutory_off', // 金
            0 => 'work',
            1 => 'work',
            2 => 'work',
            3 => 'work',
            6 => 'work',
        ]);

        $year = 2026; // 任意で指定（例: リクエストや設定値から）

        $fyStart = Carbon::create($year, 4, 1)->toDateString();
        $fyEnd   = Carbon::create($year + 1, 3, 31)->toDateString();

        // (1) user_id 1〜5 → 月火休日
        foreach (range(1, 5) as $uid) {
            if (User::find($uid)) {
                UserRestPattern::updateOrCreate(
                    ['user_id' => $uid, 'start_date' => $fyStart, 'rest_pattern_id' => $monTue->id],
                    ['end_date' => $fyEnd]
                );
            }
        }
        // (2) user_id 6〜10 → 木金休日
        foreach (range(6, 10) as $uid) {
            if (User::find($uid)) {
                UserRestPattern::updateOrCreate(
                    ['user_id' => $uid, 'start_date' => $fyStart, 'rest_pattern_id' => $thuFri->id],
                    ['end_date' => $fyEnd]
                );
            }
        }
    }

    private function rules(RestPattern $p, array $wk2kind): void
    {
        // 毎曜日の定義を作成（存在すれば更新）
        foreach ($wk2kind as $weekday => $kind) {
            RestPatternRule::updateOrCreate(
                ['rest_pattern_id' => $p->id, 'weekday' => $weekday],
                ['kind' => $kind]
            );
        }
    }
}
