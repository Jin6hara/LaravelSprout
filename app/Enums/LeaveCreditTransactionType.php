<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LeaveCreditTransactionType: string
{
    use HasValues;

    case Grant = 'grant';
    case Consume = 'consume';
    case Revert = 'revert';
    case Adjust = 'adjust';
}
