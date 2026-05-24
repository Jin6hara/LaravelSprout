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
            'SUN_MON' => ['name' => 'Sun & Mon Off', 'statutory_weekday' => 0, 'off_weekdays' => [0, 1]],
            'SUN_TUE' => ['name' => 'Sun & Tue Off', 'statutory_weekday' => 0, 'off_weekdays' => [0, 2]],
            'SUN_WED' => ['name' => 'Sun & Wed Off', 'statutory_weekday' => 0, 'off_weekdays' => [0, 3]],
            'SUN_THU' => ['name' => 'Sun & Thu Off', 'statutory_weekday' => 0, 'off_weekdays' => [0, 4]],
            'SUN_FRI' => ['name' => 'Sun & Fri Off', 'statutory_weekday' => 0, 'off_weekdays' => [0, 5]],
            'SUN_SAT' => ['name' => 'Sun & Sat Off', 'statutory_weekday' => 0, 'off_weekdays' => [0, 6]],

            'MON_TUE' => ['name' => 'Mon & Tue Off', 'statutory_weekday' => 1, 'off_weekdays' => [1, 2]],
            'MON_WED' => ['name' => 'Mon & Wed Off', 'statutory_weekday' => 1, 'off_weekdays' => [1, 3]],
            'MON_THU' => ['name' => 'Mon & Thu Off', 'statutory_weekday' => 1, 'off_weekdays' => [1, 4]],
            'MON_FRI' => ['name' => 'Mon & Fri Off', 'statutory_weekday' => 1, 'off_weekdays' => [1, 5]],

            'TUE_WED' => ['name' => 'Tue & Wed Off', 'statutory_weekday' => 3, 'off_weekdays' => [2, 3]],
            'TUE_THU' => ['name' => 'Tue & Thu Off', 'statutory_weekday' => 4, 'off_weekdays' => [2, 4]],
            'TUE_FRI' => ['name' => 'Tue & Fri Off', 'statutory_weekday' => 5, 'off_weekdays' => [2, 5]],

            'WED_THU' => ['name' => 'Wed & Thu Off', 'statutory_weekday' => 4, 'off_weekdays' => [3, 4]],
            'WED_FRI' => ['name' => 'Wed & Fri Off', 'statutory_weekday' => 5, 'off_weekdays' => [3, 5]],

            'THU_FRI' => ['name' => 'Thu & Fri Off', 'statutory_weekday' => 5, 'off_weekdays' => [4, 5]],
            'FRI_SAT' => ['name' => 'Fri & Sat Off', 'statutory_weekday' => 6, 'off_weekdays' => [5, 6]],

            'HIJOKIN' => ['name' => 'Hijokin', 'statutory_weekday' => null, 'off_weekdays' => []],
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
