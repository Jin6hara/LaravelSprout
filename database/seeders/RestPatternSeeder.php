<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{RestPattern, RestPatternRule, UserRestPattern, User};
use Carbon\Carbon;

class RestPatternSeeder extends Seeder
{
    public function run(): void
    {
        $patterns = [
            'SUN_MON' => ['name' => '日月休日', 'statutory_weekday' => 0, 'off_weekdays' => [0, 1]],
            'SUN_TUE' => ['name' => '日火休日', 'statutory_weekday' => 0, 'off_weekdays' => [0, 2]],
            'SUN_WED' => ['name' => '日水休日', 'statutory_weekday' => 0, 'off_weekdays' => [0, 3]],
            'SUN_THU' => ['name' => '日木休日', 'statutory_weekday' => 0, 'off_weekdays' => [0, 4]],
            'SUN_FRI' => ['name' => '日金休日', 'statutory_weekday' => 0, 'off_weekdays' => [0, 5]],
            'SUN_SAT' => ['name' => '日土休日', 'statutory_weekday' => 0, 'off_weekdays' => [0, 6]],

            'MON_TUE' => ['name' => '月火休日', 'statutory_weekday' => 1, 'off_weekdays' => [1, 2]],
            'MON_WED' => ['name' => '月水休日', 'statutory_weekday' => 1, 'off_weekdays' => [1, 3]],
            'MON_THU' => ['name' => '月木休日', 'statutory_weekday' => 1, 'off_weekdays' => [1, 4]],
            'MON_FRI' => ['name' => '月金休日', 'statutory_weekday' => 1, 'off_weekdays' => [1, 5]],

            'TUE_WED' => ['name' => '火水休日', 'statutory_weekday' => 3, 'off_weekdays' => [2, 3]],
            'TUE_THU' => ['name' => '火木休日', 'statutory_weekday' => 4, 'off_weekdays' => [2, 4]],
            'TUE_FRI' => ['name' => '火金休日', 'statutory_weekday' => 5, 'off_weekdays' => [2, 5]],

            'WED_THU' => ['name' => '水木休日', 'statutory_weekday' => 4, 'off_weekdays' => [3, 4]],
            'WED_FRI' => ['name' => '水金休日', 'statutory_weekday' => 5, 'off_weekdays' => [3, 5]],

            'THU_FRI' => ['name' => '木金休日', 'statutory_weekday' => 5, 'off_weekdays' => [4, 5]],
            'FRI_SAT' => ['name' => '金土休日', 'statutory_weekday' => 6, 'off_weekdays' => [5, 6]],

            'HIJOKIN' => ['name' => '非常勤', 'statutory_weekday' => null, 'off_weekdays' => []],
        ];

        foreach ($patterns as $code => $data) {
            $pattern = RestPattern::firstOrCreate(
                ['code' => $code],
                ['name' => $data['name']]
            );

            $pattern->update([
                'name' => $data['name'],
            ]);

            $this->rules(
                $pattern,
                $data['off_weekdays'],
                $data['statutory_weekday']
            );
        }

        $year = 2026;

        $fyStart = Carbon::create($year, 4, 1)->toDateString();
        $fyEnd   = Carbon::create($year + 1, 3, 31)->toDateString();

        $monTue = RestPattern::where('code', 'MON_TUE')->first();
        $thuFri = RestPattern::where('code', 'THU_FRI')->first();

        if ($monTue) {
            foreach (range(1, 5) as $uid) {
                if (User::find($uid)) {
                    UserRestPattern::updateOrCreate(
                        [
                            'user_id' => $uid,
                            'start_date' => $fyStart,
                            'rest_pattern_id' => $monTue->id,
                        ],
                        [
                            'end_date' => $fyEnd,
                        ]
                    );
                }
            }
        }

        if ($thuFri) {
            foreach (range(6, 10) as $uid) {
                if (User::find($uid)) {
                    UserRestPattern::updateOrCreate(
                        [
                            'user_id' => $uid,
                            'start_date' => $fyStart,
                            'rest_pattern_id' => $thuFri->id,
                        ],
                        [
                            'end_date' => $fyEnd,
                        ]
                    );
                }
            }
        }
    }

    private function rules(RestPattern $pattern, array $offWeekdays, ?int $statutoryWeekday): void
    {
        foreach (range(0, 6) as $weekday) {
            $kind = 'work';

            if (in_array($weekday, $offWeekdays, true)) {
                $kind = $weekday === $statutoryWeekday
                    ? 'statutory_off'
                    : 'prescribed_off';
            }

            RestPatternRule::updateOrCreate(
                [
                    'rest_pattern_id' => $pattern->id,
                    'weekday' => $weekday,
                ],
                [
                    'kind' => $kind,
                ]
            );
        }
    }
}
