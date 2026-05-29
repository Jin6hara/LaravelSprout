<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum RestPatternRuleKind: string
{
    use HasValues;

    case Work = 'work';
    case PrescribedOff = 'prescribed_off';
    case StatutoryOff = 'statutory_off';

    public function title(): string
    {
        return match ($this) {
            self::Work => '',
            self::PrescribedOff => 'ORD',
            self::StatutoryOff => 'LRD',
        };
    }
}
