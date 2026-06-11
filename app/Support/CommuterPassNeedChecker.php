<?php

/**
 * 通勤パターンと既存定期券の情報をもとに、定期券の新規取得が必要か判定するユーティリティクラス。
 */
namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;

final class CommuterPassNeedChecker
{
    /**
     * 現在のビュー構造（ユーザー内の学校ごとの配列）を受け取り判定
     *
     * 判定:
     *  1) baseDate 時点で有効な定期券がある → false（不要）
     *  2) baseDate〜baseDate+28日 と重なる schedule_lines が
     *     同じ学校で distinct 曜日数 minCount 以上ある → true（必要）
     *  3) それ以外 → false
     */
    public static function needsPassBySchools(
        array $schoolsInfo,
        Collection $userPasses,
        ?string $asOf = null,
        int $minCount = 5
    ): bool {
        $baseDate = $asOf ? Carbon::parse($asOf)->startOfDay() : now()->startOfDay();
        $rangeEnd = $baseDate->copy()->addDays(28)->endOfDay();

        // 1. baseDate 時点で有効な定期券があれば不要
        $hasActivePass = $userPasses->contains(function ($p) use ($baseDate) {
            $from = Carbon::parse($p->date_from)->startOfDay();
            $to   = Carbon::parse($p->date_to)->endOfDay();

            return $from->lte($baseDate) && $to->gte($baseDate);
        });

        if ($hasActivePass) {
            return false;
        }

        // 2. 同じ学校で、baseDate〜baseDate+28日 と重なる dow が minCount 以上あれば必要
        foreach ($schoolsInfo as $info) {
            $distinctDows = collect($info['lines'] ?? [])
                ->filter(function ($line) use ($baseDate, $rangeEnd) {
                    $lineStart = Carbon::parse($line->effective_start)->startOfDay();
                    $lineEnd   = Carbon::parse($line->effective_end)->endOfDay();

                    return $lineStart->lte($rangeEnd)
                        && $lineEnd->gte($baseDate);
                })
                ->pluck('dow')
                ->unique()
                ->count();

            if ($distinctDows >= $minCount) {
                return true;
            }
        }

        return false;
    }
}
