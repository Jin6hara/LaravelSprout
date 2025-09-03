<?php

use App\Services\Calendar\EventType;
use App\Services\Calendar\PlanGroup;

return [
    'providers' => [
        // REGULAR PLAN
        App\Services\Calendar\Providers\HolidayProvider::class    => ['level' => 1, 'type' => EventType::BACKGROUND, 'plan' => PlanGroup::REGULAR_PLAN],
        App\Services\Calendar\Providers\AdjustingProvider::class  => ['level' => 2, 'type' => EventType::BACKGROUND, 'plan' => PlanGroup::REGULAR_PLAN], // RWDはProvider内でtype=ONに変える
        App\Services\Calendar\Providers\OffDayProvider::class     => ['level' => 3, 'type' => EventType::BACKGROUND, 'plan' => PlanGroup::REGULAR_PLAN],
        App\Services\Calendar\Providers\ClosureProvider::class    => ['level' => 4, 'type' => EventType::BACKGROUND, 'plan' => PlanGroup::REGULAR_PLAN],
        App\Services\Calendar\Providers\WorkProvider::class       => ['level' => 5, 'type' => EventType::ON,         'plan' => PlanGroup::REGULAR_PLAN],

        // EVENT（例：有給/残業）— まずは空でもOK
        App\Services\Calendar\Providers\LeaveProvider::class      => ['level' => 1, 'type' => EventType::OFF,        'plan' => PlanGroup::EVENT],
        App\Services\Calendar\Providers\EventProvider::class      => ['level' => 2, 'type' => EventType::ON,         'plan' => PlanGroup::EVENT],
    ],

    'rules' => [
        'regular_plan_only_one_per_day' => true,
        'off_overrides_on'              => true,
        'on_adds_to_background'         => true,
        'holiday_untouchable'           => true,
    ],
];
