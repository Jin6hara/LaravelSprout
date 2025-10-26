<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;

final class CommuterPassNeedChecker
{
    public static function needsPass(
        array $schoolsInfo,
        Collection $userPasses,
        string $searchDate,
        int $windowDays = 30,
        int $minDaysPerWeek = 5
    ): bool {
        // 1) 検索日に有効な定期券があるなら不要
        $d = Carbon::parse($searchDate)->toDateString();
        $hasActivePass = $userPasses->contains(function ($p) use ($d) {
            return ($p->date_from <= $d && $p->date_to >= $d);
        });
        if ($hasActivePass) {
            return false;
        }

        // 2) 評価期間（検索日から windowDays 日）を用意
        $start = Carbon::parse($searchDate)->startOfDay();
        $end   = (clone $start)->addDays($windowDays - 1);

        // 3) フル週（Mon〜Sun）に限定
        //    先頭は次の月曜日、末尾は直前/同日の日曜日に丸める
        $evalStart = $start->isMonday() ? $start->copy() : $start->copy()->next(Carbon::MONDAY);
        $evalEnd   = $end->isSunday()   ? $end->copy()   : $end->copy()->previous(Carbon::SUNDAY);

        // フル週が一つも取れない場合は「不要」とみなす（運用に合わせて必要なら true に変更可）
        if ($evalStart->gt($evalEnd)) {
            return false;
        }

        foreach ($schoolsInfo as $schoolName => $info) {
            $lines = collect($info['lines'] ?? []);
            if ($lines->isEmpty()) {
                continue;
            }

            // evalStart から 1週間ずつチェック（常に Mon〜Sun のフル週）
            $weekStart = $evalStart->copy();
            $okAllWeeks = true;

            while ($weekStart->lte($evalEnd)) {
                $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

                // その週の実勤務日数（同一 school で該当 dow が期間内に有効）
                $daysPresent = 0;
                $day = $weekStart->copy();
                while ($day->lte($weekEnd)) {
                    $dow = (int) $day->dayOfWeek; // 0=Sun ... 6=Sat
                    $exists = $lines->first(function ($line) use ($dow, $day) {
                        $ls = Carbon::parse($line->effective_start);
                        $le = Carbon::parse($line->effective_end);
                        return ((int) $line->dow === $dow) && $ls->lte($day) && $le->gte($day);
                    });
                    if ($exists) {
                        $daysPresent++;
                    }
                    $day->addDay();
                }

                if ($daysPresent < $minDaysPerWeek) {
                    $okAllWeeks = false;
                    break;
                }

                // 次の週へ
                $weekStart->addWeek();
            }

            // どれか1校でも全フル週クリアなら「定期券必要」
            if ($okAllWeeks) {
                return true;
            }
        }

        return false;
    }
}
