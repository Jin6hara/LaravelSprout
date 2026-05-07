<?php

namespace App\Support;

final class SchoolName
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value, " \t\n\r\0\x0B\xC2\xA0\xE3\x80\x80");
        if ($trimmed === '') {
            return '';
        }

        return self::key($trimmed) === 'sub' ? 'Sub' : $trimmed;
    }

    public static function key(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return mb_strtolower(trim($value, " \t\n\r\0\x0B\xC2\xA0\xE3\x80\x80"), 'UTF-8');
    }
}
