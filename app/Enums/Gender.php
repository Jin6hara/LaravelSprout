<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum Gender: string
{
    use HasValues;

    case Male = 'male';
    case Female = 'female';
    case Other = 'other';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Male => '男性',
            self::Female => '女性',
            self::Other => 'その他',
            self::Unknown => '未選択',
        };
    }
}
