<?php

/**
 * Enumの全ケースのvalue一覧を配列で取得するvalues()メソッドを提供するトレイト。
 */
namespace App\Enums\Concerns;

trait HasValues
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
