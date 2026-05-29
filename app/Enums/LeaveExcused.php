<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LeaveExcused: string
{
    use HasValues;

    case Excused = 'excused';
    case Unexcused = 'unexcused';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Excused => 'Excused',
            self::Unexcused => 'Unexcused',
            self::Unknown => 'Unknown',
        };
    }

    public static function managedValues(): array
    {
        return [
            self::Excused->value,
            self::Unexcused->value,
        ];
    }

    public static function managedLabels(): array
    {
        return [
            self::Excused->value => self::Excused->label(),
            self::Unexcused->value => self::Unexcused->label(),
        ];
    }
}
