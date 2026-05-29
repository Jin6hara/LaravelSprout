<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum LeaveCreditTransactionType: string
{
    use HasValues;

    case Grant = 'Grant';
    case Consume = 'Consume';
    case Revert = 'Revert';
    case Adjust = 'Adjust';
}
