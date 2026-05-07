<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;

final class CommuterPassNeedChecker
{
    /**
     * 学校ごとの schedule_lines 集合で判定（検索日に有効な line のみを対象）
     *
     * 条件:
     *  1) as-of 時点で有効な line 数が 5件以上
     *  2) 各 line の (effective_start ～ effective_end) が 28日以上 かつ
     *     その期間に有効な定期券が存在しない
     *  3) 全 line の school_name が同一
     *
     * すべて満たす → true（定期券要）
     */
    public static function needsPassForLines(
        Collection $lines,
        Collection $userPasses,
        ?string $asOf = null // 例: '2025-11-04'
    ): bool {
        $asOfDate = $asOf ? Carbon::parse($asOf)->startOfDay() : null;

        // ▼ as-of 指定があれば、その日時点で有効な line のみに絞る
        if ($asOfDate) {
            $lines = $lines->filter(function ($line) use ($asOfDate) {
                $start = Carbon::parse($line->effective_start)->startOfDay();
                $end   = Carbon::parse($line->effective_end)->endOfDay();
                return $start->lte($asOfDate) && $end->gte($asOfDate);
            })->values();
        }

        // 条件1：本数 >= 5
        if ($lines->count() < 5) {
            return false;
        }

        // 条件3：学校名が全て同じ
        $schools = $lines->pluck('school_name')
            ->map(fn($school) => SchoolName::key($school))
            ->filter()
            ->unique();
        if ($schools->count() !== 1) {
            return false;
        }

        // 条件2：各 line の「検索日以降の残存期間」が 28日以上 & その残存期間に有効な定期券なし
        foreach ($lines as $line) {
            $start = Carbon::parse($line->effective_start)->startOfDay();
            $end   = Carbon::parse($line->effective_end)->endOfDay();

            // 検索日以降に限定した残存ウィンドウ
            $winStart = $asOfDate ? $asOfDate->copy() : $start;
            if ($winStart->lt($start)) {
                $winStart = $start->copy();
            }
            $winEnd = $end;

            // 残存がない（asOf > end）のケースは先に弾かれているはずだが念のため
            if ($winStart->gt($winEnd)) {
                return false;
            }

            $remainingDaysInclusive = $winStart->diffInDays($winEnd) + 1;
            if ($remainingDaysInclusive < 29) {
                return false; // ← ここで 11/11〜11/30 は 20日 ⇒ NG
            }

            // 定期券の重なりは「残存ウィンドウ」とのオーバーラップで判定
            $hasPassOverlap = $userPasses->contains(function ($p) use ($winStart, $winEnd) {
                $from = Carbon::parse($p->date_from)->startOfDay();
                $to   = Carbon::parse($p->date_to)->endOfDay();
                return $from->lte($winEnd) && $to->gte($winStart);
            });
            if ($hasPassOverlap) {
                return false;
            }
        }

        return true;
    }

    /**
     * 現在のビュー構造（ユーザー内の学校ごとの配列）を受け取り、
     * どれか1学校でも条件を満たせば true
     */
    public static function needsPassBySchools(
        array $schoolsInfo,
        Collection $userPasses,
        ?string $asOf = null
    ): bool {
        foreach ($schoolsInfo as $school => $info) {
            $lines = collect($info['lines'] ?? []);
            if ($lines->isEmpty()) {
                continue;
            }
            if (self::needsPassForLines($lines, $userPasses, $asOf)) {
                return true;
            }
        }
        return false;
    }
}
