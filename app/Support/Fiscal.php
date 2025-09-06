<?php

namespace App\Support;

use Carbon\Carbon;

class Fiscal
{
    // 4/1開始FY（FY2025 = 2025/04/01〜）
    public static function fyKey(Carbon $date): string
    {
        $y = (int)$date->year;
        // 1-3月は前FYに属する
        if ((int)$date->month <= 3) $y -= 1;
        return 'FY' . $y;
    }
}
