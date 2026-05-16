<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\{RestPattern, RestPatternAdjustment};

class RestPatternAdjustmentSeeder extends Seeder
{
    public function run(): void
    {
        $adjustments = [
            'SUN_MON' => [
                'add_off' => [
                    '2025-04-01',
                ],
                'work_instead' => [],
            ],
            'SUN_TUE' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-10-13',
                    '2026-01-12',
                    '2026-02-09',
                ],
            ],
            'SUN_WED' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-10-14',
                    '2025-11-25',
                    '2026-01-13',
                    '2026-02-24',
                ],
            ],
            'SUN_THU' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-11-26',
                    '2026-01-14',
                    '2026-02-25',
                ],
            ],
            'SUN_FRI' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-05-08',
                    '2025-11-06',
                    '2026-01-15',
                    '2026-02-26',
                ],
            ],
            'SUN_SAT' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-05-09',
                    '2025-09-26',
                    '2025-11-07',
                    '2026-02-27',
                ],
            ],

            'MON_TUE' => [
                'add_off' => [
                    '2025-04-01',
                ],
                'work_instead' => [],
            ],
            'MON_WED' => [
                'add_off' => [],
                'work_instead' => [],
            ],
            'MON_THU' => [
                'add_off' => [
                    '2025-04-03',
                ],
                'work_instead' => [],
            ],
            'MON_FRI' => [
                'add_off' => [],
                'work_instead' => [],
            ],

            'TUE_WED' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-04-28',
                    '2025-07-21',
                    '2025-11-24',
                    '2026-01-12',
                ],
            ],
            'TUE_THU' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-04-28',
                    '2025-07-21',
                    '2026-01-12',
                ],
            ],
            'TUE_FRI' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-04-28',
                    '2025-11-24',
                    '2026-01-12',
                    '2026-02-09',
                ],
            ],

            'WED_THU' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-10-14',
                    '2025-11-25',
                    '2026-01-13',
                    '2026-03-24',
                ],
            ],
            'WED_FRI' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-10-14',
                    '2025-11-25',
                    '2026-01-13',
                    '2026-02-24',
                    '2026-03-24',
                ],
            ],

            'THU_FRI' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-10-15',
                    '2025-11-26',
                    '2026-01-14',
                    '2026-02-25',
                ],
            ],
            'FRI_SAT' => [
                'add_off' => [],
                'work_instead' => [
                    '2025-07-24',
                    '2025-10-16',
                    '2025-11-27',
                    '2026-02-26',
                    '2026-03-26',
                ],
            ],

            'HIJOKIN' => [
                'add_off' => [
                    '2025-12-23',
                    '2026-01-05',
                ],
                'work_instead' => [],
            ],
        ];

        foreach ($adjustments as $code => $items) {
            $pattern = RestPattern::where('code', $code)->first();

            if (!$pattern) {
                continue;
            }

            foreach ($items['add_off'] as $date) {
                RestPatternAdjustment::updateOrCreate(
                    [
                        'rest_pattern_id' => $pattern->id,
                        'date' => $date,
                    ],
                    [
                        'kind' => 'add_off',
                        'code' => 'ORD',
                        'title' => 'Adjustment ORD',
                        'is_active' => true,
                    ]
                );
            }

            foreach ($items['work_instead'] as $date) {
                RestPatternAdjustment::updateOrCreate(
                    [
                        'rest_pattern_id' => $pattern->id,
                        'date' => $date,
                    ],
                    [
                        'kind' => 'work_instead',
                        'code' => 'RWD',
                        'title' => 'Rostered Working Day',
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
