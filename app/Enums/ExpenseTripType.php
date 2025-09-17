<?php

namespace App\Enums;

enum ExpenseTripType: string
{
    case ROUND_TRIP = 'round_trip';
    case ONE_WAY    = 'one_way';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
