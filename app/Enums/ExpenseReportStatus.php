<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ExpenseReportStatus: string
{
    use HasValues;

    case DRAFT     = 'draft';
    case SUBMITTED = 'submitted';
    case APPROVED  = 'approved';
    case PAID      = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::APPROVED => 'Approved',
            self::PAID => 'Paid',
        };
    }
}
