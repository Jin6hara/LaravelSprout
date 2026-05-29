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
}
