<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum DayOfWeek: string
{
    use HasValues;

    case Mon = 'Mon';
    case Tue = 'Tue';
    case Wed = 'Wed';
    case Thu = 'Thu';
    case Fri = 'Fri';
    case Sat = 'Sat';
    case Sun = 'Sun';

}
