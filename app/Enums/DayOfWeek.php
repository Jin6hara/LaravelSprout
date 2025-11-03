<?php

namespace App\Enums;

enum DayOfWeek: string
{
    case Mon = 'Mon';
    case Tue = 'Tue';
    case Wed = 'Wed';
    case Thu = 'Thu';
    case Fri = 'Fri';
    case Sat = 'Sat';
    case Sun = 'Sun';

    public static function values(): array
    {
        return array_map(fn($c) => $c->value, self::cases());
    }
}
