<?php

namespace App\Services;

use App\Models\User;
use App\Models\Schedule;
use App\Models\ScheduleLine;
use App\Models\ScheduleDetail;
use App\Models\Lesson;
use App\Models\LessonStartTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserScheduleLineCsvImportService
{
    /**
     * 受け入れるCSVヘッダ（別名対応）:
     *  - Employ Code ('user_id') / employee_code / user_id
     *  - Type ('label') / label
     *  - Total Minutes ('total_minutes') / total_minutes
     *  - Contract Start ('effective_start') / schedule_start / effective_start
     *  - Contract End   ('effective_end')   / schedule_end   / effective_end
     *
     *  - School ('school_name') / school_name
     *  - DOW ('dow') / dow      ← 1:月 ... 7:日 を受け取り 7→0 に変換
     *  - Shift From ('start_time') / start_time   ← "H:i"
     *  - Shift To   ('end_time')   / end_time     ← "H:i"
     *  - Line Start ('effective_start') / line_start / line_effective_start
     *  - Line End   ('effective_end')   / line_end   / line_effective_end
     *
     *  - Start At ('lesson_start_times') / lesson_start_time / start_at ← "H:i"
     *  - Lesson Name ('lesson_code') / lesson_code
     *  - Lesson Start ('effective_start') / lesson_start / lesson_effective_start
     *  - Lesson End   ('effective_end')   / lesson_end   / lesson_effective_end
     *
     * 注意:
     *  - DB格納は date=YYYY-MM-DD, time=HH:MM:SS（秒は :00 で固定）。UI表示は H:i にしてください。
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

        // ヘッダのBOM/空白除去
        $header = array_map([$this, 'cleanStr'], $header);

        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'missing_user' => 0, 'invalid' => 0];
        $logs    = [];

        DB::transaction(function () use ($fp, $header, $doUpdate, &$summary, &$logs) {
            while ($row = fgetcsv($fp)) {
                $raw = @array_combine($header, $row);
                if ($raw === false) {
                    $summary['invalid']++;
                    $logs[] = '列数不一致の行をスキップ';
                    continue;
                }
                // 値のBOM/空白除去
                foreach ($raw as $k => $v) if (is_string($v)) $raw[$k] = $this->cleanStr($v);

                // === 別名マッピング ===
                $get = function (array $keys) use ($raw) {
                    foreach ($keys as $k) if (array_key_exists($k, $raw)) return $raw[$k];
                    return null;
                };

                // スケジュール（契約）ヘッダ
                $empCode  = $get(['user_id', 'employee_code', 'Employ Code (\'user_id\')', 'Employ Code', '﻿user_id']);
                $label    = $get(['label', "Type ('label')", 'Type', 'schedule_label']);
                $minutes  = $get(['total_minutes', "Total Minutes ('total_minutes')", 'Total Minutes']);
                $schFrom  = $get(['effective_start', "Contract Start ('effective_start')", 'Contract Start', 'schedule_start']);
                $schTo    = $get(['effective_end', "Contract End ('effective_end')", 'Contract End', 'schedule_end']);

                // ライン（器）
                $school   = $get(['school_name', "School ('school_name')", 'School']);
                $dowIn    = $get(['dow', "DOW ('dow')", 'DOW']);
                $shiftFr  = $get(['start_time', "Shift From ('start_time')", 'Shift From']);
                $shiftTo  = $get(['end_time', "Shift To ('end_time')", 'Shift To']);
                $lineFrom = $get(['line_effective_start', 'line_start', "Line Start ('effective_start')", 'Line Start', 'effective_start']);
                $lineTo   = $get(['line_effective_end', 'line_end', "Line End ('effective_end')", 'Line End', 'effective_end']);

                // ディテール（中身）
                $lessonStartAt = $get(['lesson_start_time', 'lesson_start_times', 'start_at', "Start At ('lesson_start_times')", 'Start At']);
                $lessonCode    = $get(['lesson_code', "Lesson Name ('lesson_code')", 'Lesson Name']);
                $detFrom       = $get(['lesson_effective_start', 'lesson_start', "Lesson Start ('effective_start')", 'Lesson Start', 'effective_start']);
                $detTo         = $get(['lesson_effective_end', 'lesson_end', "Lesson End ('effective_end')", 'Lesson End', 'effective_end']);

                // === 正規化 ===
                $empCode = $this->normalizeEmp($empCode);
                $label   = ($label !== null) ? trim($label) : null;
                $totalMinutes = $this->toIntOrNull($minutes);

                $schFrom = $this->toDate($schFrom);
                $schTo   = $this->toDate($schTo);

                $dow = $this->toDowIndex17($dowIn); // 1..7 → 0..6 (7を0に)
                $school = $school !== null ? trim($school) : null;
                $lineStart = $this->toTimeHms($shiftFr); // "H:i"→"H:i:00"
                $lineEnd   = $this->toTimeHms($shiftTo);

                $lineFrom = $this->toDate($lineFrom);
                $lineTo   = $this->toDate($lineTo);

                $lessonStartAt = $this->toTimeHms($lessonStartAt);
                $lessonCode    = $lessonCode !== null ? trim($lessonCode) : null;

                $detFrom = $this->toDate($detFrom);
                $detTo   = $this->toDate($detTo);

                // === 必須チェック ===
                if (!$empCode || !$schFrom || !$schTo) {
                    $summary['invalid']++;
                    $logs[] = "必須不足(emp/from/to) → " . json_encode($raw, JSON_UNESCAPED_UNICODE);
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
                if ($schFrom > $schTo || ($lineTo && $lineFrom > $lineTo) || ($detTo && $detFrom > $detTo)) {
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

                // === 2) スケジュール作成/更新 ===
                $schKey = [
                    'user_id'         => $user->id,
                    'effective_start' => $schFrom,
                    'effective_end'   => $schTo,
                    'label'           => $label,
                ];

                if ($doUpdate) {
                    $schedule = Schedule::updateOrCreate(
                        $schKey,
                        ['total_minutes' => $totalMinutes ?? 0, 'is_active' => true]
                    );
                    $schedule->wasRecentlyCreated ? $summary['created']++ : $summary['updated']++;
                } else {
                    $schedule = Schedule::firstOrCreate(
                        $schKey,
                        ['total_minutes' => $totalMinutes ?? 0, 'is_active' => true]
                    );
                    $schedule->wasRecentlyCreated ? $summary['created']++ : $summary['skipped']++;
                }

                // === 3) ライン（器） firstOrCreate ===
                $line = ScheduleLine::firstOrCreate([
                    'schedule_id'     => $schedule->id,     // ← 裏で schedule_id を紐付け
                    'dow'             => $dow,
                    'school_name'     => $school,
                    'start_time'      => $lineStart,        // DBは "H:i:00"
                    'end_time'        => $lineEnd,
                    'effective_start' => $lineFrom,
                    'effective_end'   => $lineTo,
                ]);

                // === 4) ディテール（中身） upsert ===
                $lsTimeId = LessonStartTime::where('start_time', $lessonStartAt)->value('id');
                $lessonId = Lesson::where('lesson_code', $lessonCode)->value('id');

                if (!$lsTimeId || !$lessonId) {
                    $summary['invalid']++;
                    $logs[] = "マスタ未解決(lesson_start_time_id/lesson_id) start_at={$lessonStartAt}, code={$lessonCode}";
                    continue;
                }

                ScheduleDetail::updateOrCreate(
                    [
                        'schedule_line_id'     => $line->id, // ← 裏で schedule_line_id を紐付け
                        'lesson_start_time_id' => $lsTimeId,
                        'lesson_id'            => $lessonId,
                        'effective_start'      => $detFrom,
                        'effective_end'        => $detTo,
                    ],
                    [] // 更新するカラムがあればここへ
                );
            }
        });

        return [$summary, $logs];
    }

    // ====== ヘルパ ======

    private function cleanStr(?string $s): ?string
    {
        if ($s === null) return null;
        // 先頭BOM除去 + 前後の空白/不可視空白除去
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

    /** 入力 "H:i"（例 15:00）を "H:i:00" に正規化（DBは TIME 型で秒を持つため） */
    private function toTimeHms($v): ?string
    {
        if (!$v) return null;
        $s = preg_replace('/[^\d:]/', '', (string)$v);
        // "15:00" or "15:00:00" を許可
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s)) {
            $dt = date_create($s);
            return $dt ? $dt->format('H:i:00') : null; // 秒は常に :00
        }
        // "1500" 形式も一応救済
        $digits = preg_replace('/\D/', '', $s);
        if (strlen($digits) === 3) $digits = '0' . $digits;
        if (strlen($digits) === 4) {
            $h = substr($digits, 0, 2);
            $m = substr($digits, 2, 2);
            return sprintf('%02d:%02d:00', (int)$h, (int)$m);
        }
        return null;
    }

    /** 1:月..7:日 → 0..6（7→0） / "7:日" なども許容 */
    private function toDowIndex17($v): ?int
    {
        if ($v === null || $v === '') return null;
        $s = (string)$v;
        // 先頭の数字を拾う
        if (preg_match('/^\s*(\d{1})/', $s, $m)) {
            $n = (int)$m[1];
            if ($n >= 1 && $n <= 7) {
                return $n === 7 ? 0 : $n; // 1..6は1..6, 7は0(日)
            }
        }
        // フォールバック（万一「日/月」文字だけ来たら）
        $ja = ['日' => 0, '月' => 1, '火' => 2, '水' => 3, '木' => 4, '金' => 5, '土' => 6];
        $ch = mb_substr($s, -1, 1);
        return $ja[$ch] ?? null;
    }
}
