<?php

namespace App\Services\ScheduleCsv;

use App\Models\User;
use App\Models\ScheduleLine;
use App\Models\ScheduleDetail;
use App\Models\Lesson;
use App\Support\SchoolName;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserScheduleLineCsvImportService
{
    /**
     * 受け入れるCSVヘッダ（別名対応）:
     *  - Employ Code ('user_id') / employee_code / user_id
     *  - Total Minutes ('total_minutes') / total_minutes
     *  - Line Start ('effective_start') / line_start / line_effective_start
     *  - Line End   ('effective_end')   / line_end   / line_effective_end
     *
     *  - School ('school_name') / school_name
     *  - DOW ('dow') / dow      ← 1:月 ... 7:日 を受け取り 7→0 に変換
     *  - Shift From ('start_time') / start_time   ← "H:i"
     *  - Shift To   ('end_time')   / end_time     ← "H:i"
     *
     *  - Start At ('start_time') / lesson_start_time / start_at ← "H:i"
     *  - Lesson Name ('lesson_code') / lesson_code
     *  - Lesson Start ('effective_start') / lesson_start / lesson_effective_start
     *  - Lesson End   ('effective_end')   / lesson_end   / lesson_effective_end
     */
    public function import(string $csvPath, bool $doUpdate): array
    {
        $fp = fopen($csvPath, 'r');
        if (!$fp) {
            return [['created' => 0, 'updated' => 0, 'skipped' => 0, 'missing_user' => 0, 'invalid' => 0], ['ファイルを開けません']];
        }

        $header = fgetcsv($fp);
        if (!$header) {
            return [['created' => 0, 'updated' => 0, 'skipped' => 0, 'missing_user' => 0, 'invalid' => 0], ['ヘッダがありません']];
        }

        $header = array_map([$this, 'cleanStr'], $header);

        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'missing_user' => 0, 'invalid' => 0];
        $logs    = [];

        DB::transaction(function () use ($fp, $header, $doUpdate, &$summary, &$logs) {
            while ($row = fgetcsv($fp)) {
                $raw = array_combine($header, $row);
                if ($raw === false) {
                    $summary['invalid']++;
                    $logs[] = '列数不一致の行をスキップ';
                    continue;
                }
                foreach ($raw as $k => $v) if (is_string($v)) $raw[$k] = $this->cleanStr($v);

                $get = function (array $keys) use ($raw) {
                    foreach ($keys as $k) if (array_key_exists($k, $raw)) return $raw[$k];
                    return null;
                };

                // ユーザー
                $empCode  = $get(['user_id', 'employee_code', 'Employ Code (\'user_id\')', 'Employ Code', '﻿user_id']);
                $minutes  = $get(['total_minutes', "Total Minutes ('total_minutes')", 'Total Minutes']);

                // ライン（器）
                $school   = $get(['school_name', "School ('school_name')", 'School']);
                $dowIn    = $get(['dow', "DOW ('dow')", 'DOW']);
                $shiftFr  = $get(['start_time', "Shift From ('start_time')", 'Shift From']);
                $shiftTo  = $get(['end_time', "Shift To ('end_time')", 'Shift To']);
                $lineFrom = $get(['line_effective_start', 'line_start', "Line Start ('effective_start')", 'Line Start', 'effective_start']);
                $lineTo   = $get(['line_effective_end', 'line_end', "Line End ('effective_end')", 'Line End', 'effective_end']);

                // ディテール（中身）
                $lessonStartAt = $get(['lesson_start_time', 'start_at', "Start At ('start_time')", "Start At ('lesson_start_times')", 'Start At']);
                $lessonCode    = $get(['lesson_code', "Lesson Name ('lesson_code')", 'Lesson Name']);
                $detFrom       = $get(['lesson_effective_start', 'lesson_start', "Lesson Start ('effective_start')", 'Lesson Start', 'effective_start']);
                $detTo         = $get(['lesson_effective_end', 'lesson_end', "Lesson End ('effective_end')", 'Lesson End', 'effective_end']);

                // === 正規化 ===
                $empCode = $this->normalizeEmp($empCode);
                $totalMinutes = $this->toIntOrNull($minutes);

                $dow = $this->toDowIndex17($dowIn);
                $school = SchoolName::normalize($school);
                $lineStart = $this->toTimeHms($shiftFr);
                $lineEnd   = $this->toTimeHms($shiftTo);

                $lineFrom = $this->toDate($lineFrom);
                $lineTo   = $this->toDate($lineTo);

                $lessonStartAt = $this->toTimeHms($lessonStartAt);
                $lessonCode    = $lessonCode !== null ? trim($lessonCode) : null;

                $detFrom = $this->toDate($detFrom);
                $detTo   = $this->toDate($detTo);

                // === 必須チェック ===
                if (!$empCode) {
                    $summary['invalid']++;
                    $logs[] = "必須不足(emp) → " . json_encode($raw, JSON_UNESCAPED_UNICODE);
                    continue;
                }
                if (!$school || $dow === null || !$lineStart || !$lineEnd || !$lineFrom) {
                    $summary['invalid']++;
                    $logs[] = "ライン必須不足(school/dow/shift/line_from) → " . json_encode($raw, JSON_UNESCAPED_UNICODE);
                    continue;
                }
                if (!$lessonStartAt || !$lessonCode || !$detFrom) {
                    $summary['invalid']++;
                    $logs[] = "ディテール必須不足(start_at/lesson_code/det_from) → " . json_encode($raw, JSON_UNESCAPED_UNICODE);
                    continue;
                }
                if (($lineTo && $lineFrom > $lineTo) || ($detTo && $detFrom > $detTo)) {
                    $summary['invalid']++;
                    $logs[] = "期間の前後が逆です → " . json_encode($raw, JSON_UNESCAPED_UNICODE);
                    continue;
                }

                // === 1) ユーザー特定 ===
                $user = User::where('employee_code', $empCode)->first();
                if (!$user) {
                    $summary['missing_user']++;
                    $logs[] = "ユーザー未発見 employee_code={$empCode}";
                    continue;
                }

                // === 2) ライン firstOrCreate ===
                $lineKey = [
                    'user_id'         => $user->id,
                    'dow'             => $dow,
                    'start_time'      => $lineStart,
                    'effective_start' => $lineFrom,
                ];

                if ($doUpdate) {
                    $line = ScheduleLine::updateOrCreate(
                        $lineKey,
                        [
                            'school_name'    => $school,
                            'end_time'       => $lineEnd,
                            'effective_end'  => $lineTo,
                            'total_minutes'  => $totalMinutes ?? 0,
                        ]
                    );
                    $line->wasRecentlyCreated ? $summary['created']++ : $summary['updated']++;
                } else {
                    $line = ScheduleLine::firstOrCreate(
                        $lineKey,
                        [
                            'school_name'    => $school,
                            'end_time'       => $lineEnd,
                            'effective_end'  => $lineTo,
                            'total_minutes'  => $totalMinutes ?? 0,
                        ]
                    );
                    $line->wasRecentlyCreated ? $summary['created']++ : $summary['skipped']++;
                }

                // === 3) ディテール（中身） upsert ===
                $lessonId = Lesson::where('lesson_code', $lessonCode)->value('id');

                if (!$lessonStartAt || !$lessonId) {
                    $summary['invalid']++;
                    $logs[] = "マスタ未解決(start_time/lesson_id) start_at={$lessonStartAt}, code={$lessonCode}";
                    continue;
                }

                ScheduleDetail::updateOrCreate(
                    [
                        'schedule_line_id' => $line->id,
                        'start_time'       => $lessonStartAt,
                        'lesson_id'        => $lessonId,
                        'effective_start'  => $detFrom,
                        'effective_end'    => $detTo,
                    ],
                    []
                );
            }
        });

        return [$summary, $logs];
    }

    // ====== ヘルパ ======

    private function cleanStr(?string $s): ?string
    {
        if ($s === null) return null;
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
        return trim($s, " \t\n\r\0\x0B\xC2\xA0\xE3\x80\x80");
    }

    private function normalizeEmp(?string $v): ?string
    {
        if (!$v) return null;
        $digits = preg_replace('/\D/', '', $v);
        if ($digits === '') return null;
        return str_pad($digits, 6, '0', STR_PAD_LEFT);
    }

    private function toIntOrNull($v): ?int
    {
        if ($v === null || $v === '') return null;
        return is_numeric($v) ? (int)$v : null;
    }

    private function toDate($v): ?string
    {
        if (!$v) return null;
        try {
            return Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function toTimeHms($v): ?string
    {
        if (!$v) return null;
        $s = preg_replace('/[^\d:]/', '', (string)$v);
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s)) {
            $dt = date_create($s);
            return $dt ? $dt->format('H:i:00') : null;
        }
        $digits = preg_replace('/\D/', '', $s);
        if (strlen($digits) === 3) $digits = '0' . $digits;
        if (strlen($digits) === 4) {
            $h = substr($digits, 0, 2);
            $m = substr($digits, 2, 2);
            return sprintf('%02d:%02d:00', (int)$h, (int)$m);
        }
        return null;
    }

    private function toDowIndex17($v): ?int
    {
        if ($v === null || $v === '') return null;
        $s = (string)$v;
        if (preg_match('/^\s*(\d{1})/', $s, $m)) {
            $n = (int)$m[1];
            if ($n >= 1 && $n <= 7) {
                return $n === 7 ? 0 : $n;
            }
        }
        $ja = ['日' => 0, '月' => 1, '火' => 2, '水' => 3, '木' => 4, '金' => 5, '土' => 6];
        $ch = mb_substr($s, -1, 1);
        return $ja[$ch] ?? null;
    }
}
