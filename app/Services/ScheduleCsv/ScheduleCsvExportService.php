<?php

namespace App\Services\ScheduleCsv;

use App\Models\School;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScheduleCsvExportService
{
    /** FileMaker ヘッダー順 + district_name / department_name（再import用） */
    private const HEADERS = [
        'EndDate',
        'EndTime',
        'LineID',
        'StartDate',
        'StartTime',
        'ClassSlots::cClassName',
        'ClassSlots::EndDate',
        'ClassSlots::LineID',
        'ClassSlots::StartDate',
        'ClassSlots::StartTime',
        'Days::DayName',
        'Employees ＭＡＩＮ::EmployeeCode',
        'Schools::SchoolNumName',
        'district_name',
        'department_name',
    ];

    private const DOW_NAMES = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public function streamCsv(string $filename, int $fiscalYear): StreamedResponse
    {
        $fiscalStart = sprintf('%04d-04-01', $fiscalYear);
        $fiscalEnd = sprintf('%04d-03-31', $fiscalYear + 1);

        // school_name => school_code の逆引きマップ（export 時の SchoolNumName 組み立て用）
        $schoolCodeMap = School::pluck('school_code', 'school_name')->all();

        return response()->streamDownload(function () use ($schoolCodeMap, $fiscalStart, $fiscalEnd) {
            $fp = fopen('php://output', 'w');

            fputcsv($fp, self::HEADERS);

            // schedule_lines を user / effective_start / dow / start_time 順で取得
            $lines = DB::table('schedule_lines as sl')
                ->leftJoin('users as u', 'sl.user_id', '=', 'u.id')
                ->leftJoin('districts as di', 'sl.district_id', '=', 'di.id')
                ->leftJoin('departments as dep', 'sl.department_id', '=', 'dep.id')
                ->select(
                    'sl.id as line_id',
                    'sl.dow',
                    'sl.school_name',
                    'sl.start_time as line_start_time',
                    'sl.end_time as line_end_time',
                    'sl.effective_start as line_eff_start',
                    'sl.effective_end as line_eff_end',
                    'u.employee_code',
                    'di.name as district_name',
                    'dep.name as department_name'
                )
                ->whereDate('sl.effective_start', '>=', $fiscalStart)
                ->whereDate('sl.effective_start', '<=', $fiscalEnd)
                ->orderBy('u.employee_code')
                ->orderBy('sl.effective_start')
                ->orderBy('sl.dow')
                ->orderBy('sl.start_time')
                ->get();

            foreach ($lines as $line) {
                // この schedule_line に紐付く details
                $details = DB::table('schedule_details as sd')
                    ->join('lessons as l', 'sd.lesson_id', '=', 'l.id')
                    ->where('sd.schedule_line_id', $line->line_id)
                    ->whereDate('sd.effective_start', '>=', $fiscalStart)
                    ->whereDate('sd.effective_start', '<=', $fiscalEnd)
                    ->select(
                        'l.fm_lesson_code as lesson_code',
                        'sd.start_time as det_start_time',
                        'sd.effective_start as det_eff_start',
                        'sd.effective_end as det_eff_end'
                    )
                    ->orderBy('sd.start_time')
                    ->get();

                if ($details->isEmpty()) {
                    // detail がない schedule_line は schedule_line 情報のみ出力
                    fputcsv($fp, $this->buildLineOnlyRow($line, $schoolCodeMap));
                    continue;
                }

                $schoolNumName = $this->buildSchoolNumName($line->school_name, $schoolCodeMap);
                $dayName       = self::DOW_NAMES[(int)$line->dow] ?? '';
                $first         = true;

                foreach ($details as $det) {
                    if ($first) {
                        // 1行目: schedule_line 系 + detail 系をすべて出力
                        fputcsv($fp, [
                            $this->d($line->line_eff_end),
                            $this->t($line->line_end_time),
                            $line->line_id,
                            $this->d($line->line_eff_start),
                            $this->t($line->line_start_time),
                            $det->lesson_code,
                            $this->d($det->det_eff_end),
                            $line->line_id, // ClassSlots::LineID = 同じ line_id
                            $this->d($det->det_eff_start),
                            $this->t($det->det_start_time),
                            $dayName,
                            $line->employee_code ?? '',
                            $schoolNumName,
                            $line->district_name ?? '',
                            $line->department_name ?? '',
                        ]);
                        $first = false;
                    } else {
                        // 2行目以降: schedule_line 系は空白、detail 系のみ
                        fputcsv($fp, [
                            '',             // EndDate
                            '',             // EndTime
                            '',             // LineID
                            '',             // StartDate
                            '',             // StartTime
                            $det->lesson_code,
                            $this->d($det->det_eff_end),
                            $line->line_id, // ClassSlots::LineID
                            $this->d($det->det_eff_start),
                            $this->t($det->det_start_time),
                            '',             // Days::DayName
                            '',             // Employees ＭＡＩＮ::EmployeeCode
                            '',             // Schools::SchoolNumName
                            '',             // district_name
                            '',             // department_name
                        ]);
                    }
                }
            }

            fclose($fp);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** detail がない行の出力（detail 系は空） */
    private function buildLineOnlyRow(object $line, array $schoolCodeMap): array
    {
        return [
            $this->d($line->line_eff_end),
            $this->t($line->line_end_time),
            $line->line_id,
            $this->d($line->line_eff_start),
            $this->t($line->line_start_time),
            '',  // ClassSlots::cClassName
            '',  // ClassSlots::EndDate
            $line->line_id,  // ClassSlots::LineID
            '',  // ClassSlots::StartDate
            '',  // ClassSlots::StartTime
            self::DOW_NAMES[(int)$line->dow] ?? '',
            $line->employee_code ?? '',
            $this->buildSchoolNumName($line->school_name, $schoolCodeMap),
            $line->district_name ?? '',
            $line->department_name ?? '',
        ];
    }

    /** school_name から "school_code - school_name" を組み立て。code が不明なら school_name のみ */
    private function buildSchoolNumName(?string $schoolName, array $schoolCodeMap): string
    {
        if ($schoolName === null || $schoolName === '') {
            return '';
        }

        $code = $schoolCodeMap[$schoolName] ?? null;

        if ($code !== null) {
            return $code . ' - ' . $schoolName;
        }

        return $schoolName;
    }

    /** 日付を Y/m/d 形式で返す（null は空文字） */
    private function d(?string $ymd): string
    {
        if (!$ymd) {
            return '';
        }
        // YYYY-MM-DD → YYYY/MM/DD
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return $m[1] . '/' . $m[2] . '/' . $m[3];
        }
        return $ymd;
    }

    /** 時刻を H:i 形式で返す（null は空文字） */
    private function t(?string $time): string
    {
        if (!$time) {
            return '';
        }
        if (preg_match('/^(\d{1,2}):(\d{2})/', $time, $m)) {
            return sprintf('%d:%02d', (int)$m[1], (int)$m[2]);
        }
        return $time;
    }
}
