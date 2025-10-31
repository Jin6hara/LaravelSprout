<?php

use App\Services\Calendar\EventType;
use App\Services\Calendar\PlanGroup;

return [
    'providers' => [
        // REGULAR PLAN
        App\Services\Calendar\Providers\HolidayProvider::class    => ['level' => 1, 'type' => EventType::BACKGROUND, 'plan' => PlanGroup::REGULAR_PLAN],
        App\Services\Calendar\Providers\ClosureProvider::class    => ['level' => 4, 'type' => EventType::BACKGROUND, 'plan' => PlanGroup::REGULAR_PLAN],
        App\Services\Calendar\Providers\AllLeaveProvider::class   => ['level' => 1, 'type' => EventType::ON,         'plan' => PlanGroup::EVENT       ],
    ],

    'rules' => [
        'on_adds_to_background'         => true,
        'holiday_untouchable'           => true,
    ],
];