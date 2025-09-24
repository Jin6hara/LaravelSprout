<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case REGULAR   = 'regular';
    case IRREGULAR = 'irregular';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
