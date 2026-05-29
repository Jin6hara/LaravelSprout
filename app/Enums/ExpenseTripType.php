<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ExpenseTripType: string
{
    use HasValues;

    case ROUND_TRIP = 'round_trip';
    case ONE_WAY    = 'one_way';

    public function label(): string
    {
        return match ($this) {
            self::ROUND_TRIP => 'Round trip',
            self::ONE_WAY => 'One way',
        };
    }
}
