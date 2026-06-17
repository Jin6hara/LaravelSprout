<?php

/**
 * 経費の種別（通常定期／臨時）を表すEnum。
 */
namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ExpenseCategory: string
{
    use HasValues;

    case REGULAR   = 'regular';
    case IRREGULAR = 'irregular';
}
