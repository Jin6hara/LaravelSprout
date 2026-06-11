<?php

/**
 * 休日パターン調整の種別（振替休日追加／代替出勤）を表すEnum。
 */
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
