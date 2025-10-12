<?php

//Eventモデルのシフト合計時間を計算するため

namespace App\Observers;

use App\Models\Event;
use Carbon\Carbon;

class EventObserver
{
    public function saving(Event $event): void
    {
        // start_time or end_time が null → AllDay扱い（保存しない）
        if (!$event->start_time || !$event->end_time) {
            $event->total_duration = null;
            return;
        }

        // H:i[:s] を Carbon に
        $start = Carbon::createFromFormat('H:i:s', self::norm($event->start_time));
        $end   = Carbon::createFromFormat('H:i:s', self::norm($event->end_time));

        // 終了が開始より前 → 日跨ぎとみなす（+24h）
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        $minutes = $end->diffInMinutes($start);
        $event->total_duration = self::formatHM($minutes); // "H:MM"
    }

    private static function norm(string $t): string
    {
        // "H:i" でも "H:i:s" でも受け取れるように正規化
        return strlen($t) === 5 ? $t . ':00' : $t;
    }

    private static function formatHM(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%d:%02d', $h, $m);
    }
}
