<?php

/**
 * 休暇残高の取引種別（付与／消費／差戻し／調整）を表すEnum。
 */
namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LeaveCreditTransactionType: string
{
    use HasValues;

    case Grant = 'grant';
    case Consume = 'consume';
    case Revert = 'revert';
    case Adjust = 'adjust';

    public function label(): string
    {
        return match ($this) {
            self::Grant => 'Grant',
            self::Consume => 'Consume',
            self::Revert => 'Revert',
            self::Adjust => 'Adjust',
        };
    }
}
