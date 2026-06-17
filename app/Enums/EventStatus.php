<?php

/**
 * イベント（シフト枠等）のステータス（pending/fixed/filled/in_process）を表すEnum。
 */
namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum EventStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Fixed = 'fixed';
    case Filled = 'filled';
    case InProcess = 'in_process';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Fixed => 'Fixed',
            self::Filled => 'Filled',
            self::InProcess => 'In Process',
        };
    }

    public static function assignedValues(): array
    {
        return [
            self::Pending->value,
            self::InProcess->value,
        ];
    }

    public static function fixedValues(): array
    {
        return [
            self::Fixed->value,
            self::Filled->value,
        ];
    }
}
