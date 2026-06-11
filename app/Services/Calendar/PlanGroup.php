<?php

/**
 * カレンダーイベントのプラングループ（通常勤務・イベント）を定義する定数クラス。
 */
// 元Enum 2/3
namespace App\Services\Calendar;

final class PlanGroup
{
    public const REGULAR_PLAN = 'regular_plan';
    public const EVENT        = 'event';
}
