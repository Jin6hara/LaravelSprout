<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ExpenseTripType: string
{
    use HasValues;

    case ROUND_TRIP = 'round_trip';
    case ONE_WAY    = 'one_way';
}
