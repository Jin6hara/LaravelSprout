<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ExpenseTripType: string
{
    use HasValues;

    case ROUND_TRIP = 'Round trip';
    case ONE_WAY    = 'One way';
}
