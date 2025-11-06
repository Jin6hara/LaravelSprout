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
     * 期待CSVヘッダ（推奨／英字）
     * user_id,label,total_minutes,effective_start,effective_end,
     * school_name,dow,start_time,end_time,detail_effective_start,detail_effective_end,lesson_start_time,lesson_code
     *
     * 備考：
     * - user_id は 6桁社員コード（users.employee_code と突合）
     * - schedule の期間（effective_start/effective_end）
     * - line の期間は schedule と同じでもOK。detail 側は detail_effective_* を使う（無ければ line と同一に補完）
     */
    public function import(string $csvPath, bool $doUpdate = false): array
    {
        $fh = fopen($csvPath, 'r');
        if (!$fh) return [$this->zeroSummary(), ['ファイルを開けませんでした']];

        // 1行目を生で読み、区切り文字を自動判定
        $firstLine = fgets($fh);
        if ($firstLine === false) return [$this->zeroSummary(), ['ヘッダ行が読めません']];
        $delimiter = $this->detectDelimiter($firstLine); // ← 新規
        $header = str_getcsv($firstLine, $delimiter);

        // ヘッダのBOM/空白除去
        $header = array_map(fn($h) => $this->cleanHeader($h), $header);

        $summary = [
            'sch_created' => 0,
            'sch_updated' => 0,
            'sch_skipped' => 0,
            'line_count' => 0,
            'detail_upserted' => 0,
            'detail_skipped' => 0,
            'missing_user' => 0,
            'invalid' => 0,
        ];
        $errors = [];

        // ◆ キャリーフォワード用（その行で null/空なら、直前行の値を使う）
        $carry = [
            'empCode' => null,
            'schLabel' => null,
            'schTotal' => 0,
            'schFrom' => null,
            'schTo'    => null,
        ];

        DB::transaction(function () use ($fh, $delimiter, $header, $doUpdate, &$summary, &$errors, &$carry) {
            while (($line = fgets($fh)) !== false) {
                // 区切り文字で分解
                $cols = str_getcsv($line, $delimiter);
                if (!is_array($cols)) continue;
                $raw = @array_combine($header, $cols);
                if ($raw === false) {
                    $summary['invalid']++;
                    $errors[] = '列数不一致の行をスキップ';
                    continue;
                }

                // 値のトリム（BOM/空白）
                foreach ($raw as $k => $v) if (is_string($v)) $raw[$k] = $this->cleanValue($v);

                // 列マッピング（※ aliases に複数形も追加）
                $get = function (array $keys) use ($raw) {
                    foreach ($keys as $k) if (array_key_exists($k, $raw)) return $raw[$k];
                    return null;
                };

                $empCode   = $get(['user_id', 'employee_code', '﻿user_id', 'emp', 'emp_code']);
                $schLabel  = $get(['label', 'schedule_label']);
                $schTotal  = $get(['total_minutes', 'minutes', 'total']);
                $schFrom   = $get(['effective_start', 'schedule_start', 'from', 'start']);
                $schTo     = $get(['effective_end', 'schedule_end', 'to', 'end']);

                $school    = $get(['school_name', 'school']);
                $dowRaw    = $get(['dow', 'weekday', '曜日']);
                $lineStart = $get(['start_time', 'line_start', 'start']);
                $lineEnd   = $get(['end_time', 'line_end', 'end']);
                $lineFrom  = $get(['line_effective_start', 'effective_start']); // fallback
                $lineTo    = $get(['line_effective_end', 'effective_end']);     // fallback

                $detailFrom = $get(['detail_effective_start', 'detail_start', 'effective_start_detail']);
                $detailTo   = $get(['detail_effective_end', 'detail_end', 'effective_end_detail']);

                // ★ lesson_start_time の別名を“複数形”も許可
                $lessonT    = $get(['lesson_start_time', 'lesson_start_times', 'lesson_time', 'l_start']);
                $lessonCode = $get(['lesson_code', 'code']);

                // 正規化 + キャリーフォワード適用
                $empCode = $this->normalizeEmployeeCode($empCode) ?? $carry['empCode'];
                $schFrom = $this->toDate($schFrom) ?? $carry['schFrom'];
                $schTo   = $this->toDate($schTo)   ?? $carry['schTo'];
                $schLabel = $schLabel ?? $carry['schLabel'];
                $schTotal = $this->toIntOrNull($schTotal) ?? $carry['schTotal'] ?? 0;

                // carry更新
                if ($empCode) $carry['empCode'] = $empCode;
                if ($schFrom) $carry['schFrom'] = $schFrom;
                if ($schTo)   $carry['schTo']   = $schTo;
                if ($schLabel !== null) $carry['schLabel'] = $schLabel;
                if ($schTotal !== null) $carry['schTotal'] = $schTotal;

                // 必須チェック（ユーザー & schedule 期間）
                if (!$empCode || !$schFrom || !$schTo) {
                    $summary['invalid']++;
                    $errors[] = "必須不足(emp/from/to) → " . json_encode($raw, JSON_UNESCAPED_UNICODE);
                    continue;
                }
                if ($schFrom > $schTo) {
                    $summary['invalid']++;
                    $errors[] = "schedule期間逆転(from>to) → " . json_encode($raw, JSON_UNESCAPED_UNICODE);
                    continue;
                }

                $user = User::where('employee_code', $empCode)->first();
                if (!$user) {
                    $summary['missing_user']++;
                    $errors[] = "ユーザー未発見 employee_code={$empCode}";
                    continue;
                }

                // Schedule
                $whereSch = [
                    'user_id'         => $user->id,
                    'effective_start' => $schFrom,
                    'effective_end'   => $schTo,
                    'label'           => $schLabel,
                ];
                if ($doUpdate) {
                    $schedule = Schedule::updateOrCreate($whereSch, [
                        'total_minutes' => $schTotal,
                        'is_active'     => true,
                    ]);
                    $schedule->wasRecentlyCreated ? $summary['sch_created']++ : $summary['sch_updated']++;
                } else {
                    $schedule = Schedule::firstOrCreate($whereSch, [
                        'total_minutes' => $schTotal,
                        'is_active'     => true,
                    ]);
                    $schedule->wasRecentlyCreated ? $summary['sch_created']++ : $summary['sch_skipped']++;
                }

                // Line 必須
                $dow = $this->toDowIndex($dowRaw);
                $lineStart = $this->toTimeString($lineStart);
                $lineEnd   = $this->toTimeString($lineEnd);
                $lineFrom  = $this->toDate($lineFrom) ?? $schFrom;
                $lineTo    = $this->toDate($lineTo)   ?? $schTo;

                if (!$school || $dow === null || !$lineStart || !$lineEnd) {
                    $errors[] = "line不足(school/dow/start/end) → " . json_encode($raw, JSON_UNESCAPED_UNICODE);
                    continue;
                }

                $line = ScheduleLine::firstOrCreate([
                    'schedule_id'     => $schedule->id,
                    'parent_line_id'  => null,
                    'dow'             => $dow,
                    'school_name'     => trim($school),
                    'start_time'      => $lineStart,
                    'end_time'        => $lineEnd,
                    'effective_start' => $lineFrom,
                    'effective_end'   => $lineTo,
                ]);
                $summary['line_count']++;

                // Detail
                $detailFrom = $this->toDate($detailFrom) ?? $lineFrom;
                $detailTo   = $this->toDate($detailTo)   ?? $lineTo;

                $lessonId = null;
                if ($lessonCode) $lessonId = Lesson::where('lesson_code', $lessonCode)->value('id');

                $lsTimeId = null;
                $lessonT = $this->toTimeString($lessonT);
                if ($lessonT) $lsTimeId = LessonStartTime::where('start_time', $lessonT)->value('id');

                if ($lessonId && $lsTimeId) {
                    ScheduleDetail::updateOrCreate([
                        'schedule_line_id'     => $line->id,
                        'lesson_start_time_id' => $lsTimeId,
                        'lesson_id'            => $lessonId,
                        'effective_start'      => $detailFrom,
                        'effective_end'        => $detailTo,
                    ], []);
                    $summary['detail_upserted']++;
                } else {
                    $summary['detail_skipped']++;
                    $errors[] = "detail不足(lesson_code/lesson_start_time 解決不可) → " . json_encode($raw, JSON_UNESCAPED_UNICODE);
                }
            }
        });

        return [$summary, $errors];
    }

    // ～～ 追加：区切り自動判定 ～～
    private function detectDelimiter(string $line): string
    {
        // タブ優先 → セミコロン → カンマ
        if (str_contains($line, "\t")) return "\t";
        if (str_contains($line, ";"))  return ";";
        return ","; // デフォルト
    }

    private function zeroSummary(): array
    {
        return [
            'sch_created' => 0,
            'sch_updated' => 0,
            'sch_skipped' => 0,
            'line_count' => 0,
            'detail_upserted' => 0,
            'detail_skipped' => 0,
            'missing_user' => 0,
            'invalid' => 0,
        ];
    }

    private function cleanHeader(?string $h): ?string
    {
        if ($h === null) return null;
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h); // BOM
        return trim($h, " \t\n\r\0\x0B\xC2\xA0\xE3\x80\x80");
    }

    private function cleanValue(string $v): string
    {
        $v = preg_replace('/^\xEF\xBB\xBF/', '', $v); // BOM
        return trim($v, " \t\n\r\0\x0B\xC2\xA0\xE3\x80\x80");
    }

    private function normalizeEmployeeCode($v): ?string
    {
        if ($v === null || $v === '') return null;
        $s = preg_replace('/\D/', '', (string)$v);
        if ($s === '') return null;
        return str_pad($s, 6, '0', STR_PAD_LEFT);
    }

    private function toIntOrNull($v): ?int
    {
        if ($v === null || $v === '') return null;
        return is_numeric($v) ? (int)$v : null;
    }

    /** "日月火水木金土"/"sun..sat"/0..6 → 0..6 */
    private function toDowIndex($val): ?int
    {
        if (is_numeric($val) && $val >= 0 && $val <= 6) return (int)$val;
        $s = mb_strtolower(trim((string)$val));
        $mapJa  = ['日' => 0, '月' => 1, '火' => 2, '水' => 3, '木' => 4, '金' => 5, '土' => 6];
        $mapEn3 = ['sun' => 0, 'mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6];
        if ($s !== '') {
            $ja1 = mb_substr($s, 0, 1);
            if (isset($mapJa[$ja1])) return $mapJa[$ja1];
            $en3 = substr($s, 0, 3);
            if (isset($mapEn3[$en3])) return $mapEn3[$en3];
        }
        return null;
    }

    /** 1500 / "15:00" / "15:00:00" → "HH:MM:SS" */
    private function toTimeString($v): ?string
    {
        if ($v === null || $v === '') return null;
        $digits = preg_replace('/\D/', '', (string)$v);
        if (strlen($digits) === 3) $digits = '0' . $digits;
        if (strlen($digits) === 4) {
            $h = substr($digits, 0, 2);
            $m = substr($digits, 2, 2);
            return sprintf('%02d:%02d:00', (int)$h, (int)$m);
        }
        $dt = date_create((string)$v);
        return $dt ? $dt->format('H:i:s') : null;
    }

    /** "YYYY-MM-DD" */
    private function toDate($v): ?string
    {
        if (!$v) return null;
        try {
            return Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
