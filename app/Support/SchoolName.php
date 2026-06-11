<?php

/**
 * 学校名文字列の正規化（前後空白除去・大文字統一など）を行うユーティリティクラス。
 */
namespace App\Support;

final class SchoolName
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = self::mbTrim($value);
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

        return mb_strtolower(self::mbTrim($value), 'UTF-8');
    }

    /**
     * マルチバイト安全な trim。
     * 半角スペース・タブ等に加え、NBSP (U+00A0) と全角スペース (U+3000) を除去する。
     * PHP 標準の trim() にバイト列を渡すと UTF-8 マルチバイト文字を破壊するため、
     * /u フラグ付き正規表現で文字単位で処理する。
     */
    private static function mbTrim(string $value): string
    {
        return preg_replace('/^[\s\x{00A0}\x{3000}]+|[\s\x{00A0}\x{3000}]+$/u', '', $value) ?? $value;
    }
}
