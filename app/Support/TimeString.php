<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

final class TimeString
{
    /**
     * さまざまな入力（DateTime, 'HH:MM', 'HH:MM:SS', 'YYYY-MM-DD HH:MM:SS', 'YYYY-MM-DD' 等）
     * を "HH:MM" に正規化。判定不能な場合は '00:00' を返す。
     */
    public static function normalizeToHm(mixed $raw): string
    {
        if ($raw instanceof DateTimeInterface) {
            return Carbon::instance($raw)->format('H:i');
        }

        $s = trim((string)$raw);
        if ($s === '') {
            return '00:00';
        }

        // よくあるパターンは Carbon に任せる
        try {
            // '2025-10-24 09:00:00' や '09:00:00' などはここで OK
            return Carbon::parse($s)->format('H:i');
        } catch (\Throwable $e) {
            // '9:00' / '09:00' / '09:00:00' を素直に切る
            if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s)) {
                return substr($s, 0, 5);
            }
            // 数字3〜4桁（900 / 0900）→ HH:MM
            if (preg_match('/^\d{3,4}$/', $s)) {
                $s = str_pad($s, 4, '0', STR_PAD_LEFT);
                return substr($s, 0, 2) . ':' . substr($s, 2, 2);
            }
            // 最後のフォールバック
            return '00:00';
        }
    }

    /**
     * "HH:MM" に指定分数を加算して "HH:MM" で返す。
     * 不正な値は '00:00' を返す。
     */
    public static function addMinutesHm(string $hm, int $minutes): string
    {
        $base = self::normalizeToHm($hm);

        try {
            return Carbon::createFromFormat('H:i', $base)
                ->addMinutes($minutes)
                ->format('H:i');
        } catch (\Throwable $e) {
            return '00:00';
        }
    }
}
