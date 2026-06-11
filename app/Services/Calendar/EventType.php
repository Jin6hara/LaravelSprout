<?php

/**
 * カレンダーイベントの表示タイプ（背景・ON・OFF）を定義する定数クラス。
 */
// 元Enum 1/3
namespace App\Services\Calendar;

final class EventType
{
    public const BACKGROUND = 'background';
    public const ON         = 'on';
    public const OFF        = 'off';
}
