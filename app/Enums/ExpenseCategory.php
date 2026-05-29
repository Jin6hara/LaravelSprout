<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ExpenseCategory: string
{
    use HasValues;

    case REGULAR   = 'regular';
    case IRREGULAR = 'irregular';
}
