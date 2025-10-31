<?php

namespace App\Enums;

enum ExpenseReportStatus: string
{
    case DRAFT     = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED  = 'approved';
    case PAID      = 'paid';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
