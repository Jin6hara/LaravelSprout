<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum RestPatternAdjustmentKind: string
{
    use HasValues;

    case AddOff = 'add_off';
    case WorkInstead = 'work_instead';

    public function code(): string
    {
        return match ($this) {
            self::AddOff => 'ORD',
            self::WorkInstead => 'RWD',
        };
    }
}
