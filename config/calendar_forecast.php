<?php

use App\Services\Calendar\EventType;
use App\Services\Calendar\PlanGroup;

return [
    'providers' => [
        // REGULAR PLAN
        App\Services\Calendar\Providers\HolidayProvider::class    => ['level' => 1, 'type' => EventType::BACKGROUND, 'plan' => PlanGroup::REGULAR_PLAN],
        App\Services\Calendar\Providers\ClosureProvider::class    => ['level' => 4, 'type' => EventType::BACKGROUND, 'plan' => PlanGroup::REGULAR_PLAN],
        App\Services\Calendar\Providers\SubCountProvider::class   => ['level' => 5, 'type' => EventType::BACKGROUND, 'plan' => PlanGroup::REGULAR_PLAN],
        App\Services\Calendar\Providers\AllEventProvider::class   => ['level' => 1, 'type' => EventType::ON,         'plan' => PlanGroup::EVENT       ],
    ],

    'rules' => [
        'regular_plan_only_one_per_day' => true,
        'off_overrides_on'              => true,
        'on_adds_to_background'         => true,
        'holiday_untouchable'           => true,
    ],
];

return [
    // 「Sub」を示す school_name の含意キーワード（適宜追加/変更可）
    'sub_keywords' => ['sub', 'SUB', 'Sub'],
];
