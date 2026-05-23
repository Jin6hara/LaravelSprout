<?php

//Eventモデルのシフト合計時間を計算するため

namespace App\Observers;

use App\Models\Event;
use Carbon\Carbon;
use DateTimeInterface;

class EventObserver
{
    public function saving(Event $event): void
    {
        // $event->total_duration はアクセサ経由で total_minutes を参照するため、
        // DB カラムの値は getAttributes() で取得する
        $rawDuration = $event->getAttributes()['total_duration'] ?? null;

        if (!empty($rawDuration) && (empty($event->start_time) || empty($event->end_time))) {
            return;
        }

        // どちらか欠けていれば合計はクリアして終了
        if (empty($event->start_time) || empty($event->end_time)) {
            $event->total_duration = null;
            return;
        }

        // ★ cast の影響を受けないよう raw を優先、無ければ現値
        $startRaw = $event->getRawOriginal('start_time') ?? $event->start_time;
        $endRaw   = $event->getRawOriginal('end_time')   ?? $event->end_time;

        $start = self::toCarbonTime($startRaw);
        $end   = self::toCarbonTime($endRaw);

        if (!$start || !$end) {
            // パースできない場合は合計をクリア（保存は続行）
            $event->total_duration = null;
            return;
        }

        // 日跨ぎ対応
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        $minutes = $end->diffInMinutes($start);
        $event->total_duration = self::formatHM($minutes); // "H:MM"
    }

    /**
     * いろんな入力（Carbon/時間文字列/日時文字列/全角コロンなど）を Carbon に変換
     */
    private static function toCarbonTime($value): ?Carbon
    {
        if (empty($value)) return null;

        // 1) 既に日時オブジェクトなら、その日の 00:00 を基準に時刻抽出
        if ($value instanceof DateTimeInterface) {
            $h = (int) $value->format('H');
            $m = (int) $value->format('i');
            $s = (int) $value->format('s');
            return Carbon::today()->setTime($h, $m, $s);
        }

        // 2) 文字列整形（全角コロン・空白・改行など）
        $s = trim((string) $value);
        $s = str_replace('：', ':', $s);     // 全角コロン → 半角
        $s = preg_replace('/\s+/', ' ', $s); // 余分な空白を1つに

        // 3) まず "HH:MM[:SS]" を先頭から抽出（後ろのゴミを無視）
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?/', $s, $m)) {
            $h = (int) $m[1];
            $m2 = (int) $m[2];
            $s2 = isset($m[3]) ? (int)$m[3] : 0;
            if ($h >= 0 && $h <= 23 && $m2 >= 0 && $m2 <= 59 && $s2 >= 0 && $s2 <= 59) {
                return Carbon::today()->setTime($h, $m2, $s2);
            }
        }

        // 4) それ以外は Carbon のパーサに委ね、成功したら時分秒抽出
        try {
            $dt = Carbon::parse($s);
            return Carbon::today()->setTime(
                (int)$dt->format('H'),
                (int)$dt->format('i'),
                (int)$dt->format('s')
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function formatHM(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%d:%02d', $h, $m);
    }
}
