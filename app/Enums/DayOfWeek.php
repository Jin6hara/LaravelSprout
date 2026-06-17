<?php

/**
 * 曜日（月〜日）を表すEnumで、英略称の文字列値を保持する。
 */
namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum DayOfWeek: string
{
    use HasValues;

    case Mon = 'Mon';
    case Tue = 'Tue';
    case Wed = 'Wed';
    case Thu = 'Thu';
    case Fri = 'Fri';
    case Sat = 'Sat';
    case Sun = 'Sun';

}
