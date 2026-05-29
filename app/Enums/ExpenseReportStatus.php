<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ExpenseReportStatus: string
{
    use HasValues;

    case DRAFT     = 'Draft';
    case SUBMITTED = 'Submitted';
    case APPROVED  = 'Approved';
    case PAID      = 'Paid';
}
