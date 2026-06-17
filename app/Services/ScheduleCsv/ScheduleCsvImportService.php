<?php

/**
 * FileMaker 互換 CSV からスケジュールライン・詳細をインポートするサービス。
 */
namespace App\Services\ScheduleCsv;

use App\Models\Department;
use App\Models\District;
use App\Models\Lesson;
use App\Models\ScheduleDetail;
use App\Models\ScheduleLine;
use App\Models\School;
use App\Models\User;
use App\Support\SchoolName;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleCsvImportService
{
    /** FileMaker CSV の必須ヘッダー */
    private const REQUIRED_HEADERS = [
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

    /** 空白行に前行の値を引き継ぐ schedule_line 系カラム */
    private const INHERIT_COLS = [
        'EndDate',
        'EndTime',
        'LineID',
        'StartDate',
        'StartTime',
        'Days::DayName',
        'Employees ＭＡＩＮ::EmployeeCode',
        'Schools::SchoolNumName',
        'district_name',
        'department_name',
    ];

    private const DOW_MAP = [
        'sunday'    => 0,
        'monday'    => 1,
        'tuesday'   => 2,
        'wednesday' => 3,
        'thursday'  => 4,
        'friday'    => 5,
        'saturday'  => 6,
    ];

    /**
     * @return array{
     *   errors: string[],
     *   line_created?: int,
     *   line_updated?: int,
     *   detail_created?: int,
     *   detail_updated?: int,
     * }
     */
    public function import(string $csvPath): array
    {
        $fp = fopen($csvPath, 'r');
        if (!$fp) {
            return ['errors' => ['ファイルを開けません。']];
        }

        $rawHeader = fgetcsv($fp);
        if (!$rawHeader) {
            fclose($fp);
            return ['errors' => ['ヘッダー行がありません。']];
        }

        // BOM除去 & trim
        $header = array_map(
            fn(string $h): string => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)),
            $rawHeader
        );

        // 必須ヘッダー確認
        $missing = array_diff(self::REQUIRED_HEADERS, $header);
        if (!empty($missing)) {
            fclose($fp);
            return ['errors' => ['必須ヘッダーが不足しています: ' . implode(', ', $missing)]];
        }

        // ===== 全行読み込み & 空白継承 =====
        $rows      = [];
        $prevLine  = [];
        $rowNum    = 1;

        while (($row = fgetcsv($fp)) !== false) {
            $rowNum++;

            if (count($row) < count($header)) {
                // 列数が足りない行はパディング
                $row = array_pad($row, count($header), '');
            }

            $data = array_combine($header, array_slice($row, 0, count($header)));

            // schedule_line 系カラムの空白継承
            foreach (self::INHERIT_COLS as $col) {
                $val = trim($data[$col] ?? '');
                if ($val === '') {
                    $data[$col] = $prevLine[$col] ?? '';
                } else {
                    $prevLine[$col] = $val;
                }
            }

            $rows[] = ['rowNum' => $rowNum, 'data' => $data];
        }
        fclose($fp);

        if (empty($rows)) {
            return ['errors' => ['データ行がありません。']];
        }

        // ===== 行を FileMaker LineID でグループ化 =====
        // グループ: fmLineId => ['lineData' => [...], 'firstRowNum' => N, 'details' => [...]]
        $groups      = [];
        $parseErrors = [];

        foreach ($rows as ['rowNum' => $rowNum, 'data' => $data]) {
            $fmLineId = trim($data['LineID'] ?? '');
            if ($fmLineId === '') {
                $parseErrors[] = "{$rowNum}行目: LineID が空のため処理できません。";
                continue;
            }

            // ClassSlots::LineID が空なら継承済み LineID を使う
            $detFmLineId = trim($data['ClassSlots::LineID'] ?? '');
            if ($detFmLineId === '') {
                $detFmLineId = $fmLineId;
            }

            if (!isset($groups[$fmLineId])) {
                $groups[$fmLineId] = [
                    'lineData'    => $data,
                    'firstRowNum' => $rowNum,
                    'details'     => [],
                ];
            }

            $groups[$fmLineId]['details'][] = [
                'rowNum'     => $rowNum,
                'data'       => $data,
                'detFmLineId' => $detFmLineId,
            ];
        }

        if (!empty($parseErrors)) {
            return ['errors' => $parseErrors];
        }

        // ===== マスターデータをキャッシュ =====
        $lessonMap = Lesson::whereNotNull('fm_lesson_code')
            ->pluck('id', 'fm_lesson_code')
            ->all(); // fm_lesson_code => id

        $userMap = User::whereNotNull('employee_code')
            ->pluck('id', 'employee_code')
            ->all(); // employee_code => id

        $schoolMap = School::pluck('school_name', 'school_code')->all(); // school_code => school_name

        $districtMap  = District::pluck('id', 'name')->all();  // name => id
        $departmentMap = Department::pluck('id', 'name')->all(); // name => id

        // ===== 全件バリデーション =====
        $errors = [];

        foreach ($groups as $fmLineId => $group) {
            $lineData    = $group['lineData'];
            $firstRowNum = $group['firstRowNum'];

            // employee_code バリデーション
            $empCode = trim($lineData['Employees ＭＡＩＮ::EmployeeCode'] ?? '');
            $empCode = $this->padEmployeeCode($empCode);

            if ($empCode === '') {
                $errors[] = "{$firstRowNum}行目 (LineID={$fmLineId}): EmployeeCode が空です。";
            } elseif (!array_key_exists($empCode, $userMap)) {
                $errors[] = "{$firstRowNum}行目 (LineID={$fmLineId}): EmployeeCode「{$empCode}」が users テーブルに見つかりません。";
            }

            // district_name バリデーション
            $districtName = trim($lineData['district_name'] ?? '');
            if ($districtName === '') {
                $errors[] = "{$firstRowNum}行目 (LineID={$fmLineId}): district_name が空です。";
            } elseif (!array_key_exists($districtName, $districtMap)) {
                $errors[] = "{$firstRowNum}行目 (LineID={$fmLineId}): district_name「{$districtName}」が districts テーブルに見つかりません。";
            }

            // department_name バリデーション
            $departmentName = trim($lineData['department_name'] ?? '');
            if ($departmentName === '') {
                $errors[] = "{$firstRowNum}行目 (LineID={$fmLineId}): department_name が空です。";
            } elseif (!array_key_exists($departmentName, $departmentMap)) {
                $errors[] = "{$firstRowNum}行目 (LineID={$fmLineId}): department_name「{$departmentName}」が departments テーブルに見つかりません。";
            }

            // 日付・時刻バリデーション
            foreach ([
                'EndDate'   => $lineData['EndDate']   ?? '',
                'StartDate' => $lineData['StartDate'] ?? '',
            ] as $label => $val) {
                if ($this->parseDate($val) === null) {
                    $errors[] = "{$firstRowNum}行目 (LineID={$fmLineId}): {$label}「{$val}」を日付としてパースできません。";
                }
            }

            foreach ([
                'EndTime'   => $lineData['EndTime']   ?? '',
                'StartTime' => $lineData['StartTime'] ?? '',
            ] as $label => $val) {
                if ($this->parseTime($val) === null) {
                    $errors[] = "{$firstRowNum}行目 (LineID={$fmLineId}): {$label}「{$val}」を時刻としてパースできません。";
                }
            }

            // detail バリデーション
            foreach ($group['details'] as $detail) {
                $detRowNum = $detail['rowNum'];
                $detData   = $detail['data'];

                $lessonCode = trim($detData['ClassSlots::cClassName'] ?? '');
                if ($lessonCode === '') {
                    $errors[] = "{$detRowNum}行目: ClassSlots::cClassName が空です。";
                } elseif (!array_key_exists($lessonCode, $lessonMap)) {
                    $errors[] = "{$detRowNum}行目: fm_lesson_code「{$lessonCode}」が lessons テーブルに見つかりません。";
                }

                $detStartDate = $detData['ClassSlots::StartDate'] ?? '';
                if ($this->parseDate($detStartDate) === null) {
                    $errors[] = "{$detRowNum}行目: ClassSlots::StartDate「{$detStartDate}」を日付としてパースできません。";
                }

                $detEndDate = trim($detData['ClassSlots::EndDate'] ?? '');
                if ($detEndDate !== '' && $this->parseDate($detEndDate) === null) {
                    $errors[] = "{$detRowNum}行目: ClassSlots::EndDate「{$detEndDate}」を日付としてパースできません。";
                }

                $detStartTime = $detData['ClassSlots::StartTime'] ?? '';
                if ($this->parseTime($detStartTime) === null) {
                    $errors[] = "{$detRowNum}行目: ClassSlots::StartTime「{$detStartTime}」を時刻としてパースできません。";
                }

                // ClassSlots::LineID の解決可能チェック
                $detFmLineId = $detail['detFmLineId'];
                if (!array_key_exists($detFmLineId, $groups)) {
                    $errors[] = "{$detRowNum}行目: ClassSlots::LineID={$detFmLineId} に対応するグループが見つかりません。";
                }
            }
        }

        if (!empty($errors)) {
            return ['errors' => $errors];
        }

        // ===== DB 保存（トランザクション）=====
        $lineCreated   = 0;
        $lineUpdated   = 0;
        $detailCreated = 0;
        $detailUpdated = 0;

        DB::transaction(function () use (
            $groups, $lessonMap, $userMap, $schoolMap, $districtMap, $departmentMap,
            &$lineCreated, &$lineUpdated, &$detailCreated, &$detailUpdated
        ) {
            // fmLineId => 作成/更新した schedule_lines.id のマップ
            $lineIdMap = [];

            // --- schedule_lines の作成/更新 ---
            foreach ($groups as $fmLineId => $group) {
                $lineData = $group['lineData'];

                $empCode      = $this->padEmployeeCode(trim($lineData['Employees ＭＡＩＮ::EmployeeCode']));
                $userId       = $userMap[$empCode];
                $districtId   = $districtMap[trim($lineData['district_name'])];
                $departmentId = $departmentMap[trim($lineData['department_name'])];
                $dow      = $this->toDow($lineData['Days::DayName'], $lineData['StartDate']);
                $school   = $this->resolveSchoolName($lineData['Schools::SchoolNumName'], $schoolMap);
                // SchoolName::normalize は Model の setter が行う。ここでは正規化後の値でクエリするため手動で正規化
                $schoolNorm = SchoolName::normalize($school);
                $startTime  = $this->parseTime($lineData['StartTime']);
                $endTime    = $this->parseTime($lineData['EndTime']);
                $effStart   = $this->parseDate($lineData['StartDate']);
                $effEnd     = $this->parseDate($lineData['EndDate']);

                // 重複判定: user_id + dow + school_name + start_time + effective_start
                $line = ScheduleLine::where('user_id', $userId)
                    ->where('dow', $dow)
                    ->where('school_name', $schoolNorm)
                    ->where('start_time', $startTime)
                    ->where('effective_start', $effStart)
                    ->first();

                if ($line) {
                    $line->update([
                        'end_time'      => $endTime,
                        'effective_end' => $effEnd,
                        'district_id'   => $districtId,
                        'department_id' => $departmentId,
                    ]);
                    $lineUpdated++;
                } else {
                    $line = ScheduleLine::create([
                        'user_id'        => $userId,
                        'dow'            => $dow,
                        'school_name'    => $school, // setter が normalize する
                        'start_time'     => $startTime,
                        'end_time'       => $endTime,
                        'effective_start'=> $effStart,
                        'effective_end'  => $effEnd,
                        'total_minutes'  => 0,
                        'district_id'    => $districtId,
                        'department_id'  => $departmentId,
                    ]);
                    $lineCreated++;
                }

                $lineIdMap[$fmLineId] = $line->id;
            }

            // --- schedule_details の作成/更新 ---
            foreach ($groups as $group) {
                foreach ($group['details'] as $detail) {
                    $detData     = $detail['data'];
                    $detFmLineId = $detail['detFmLineId'];
                    $lineId      = $lineIdMap[$detFmLineId];

                    $lessonCode    = trim($detData['ClassSlots::cClassName']);
                    $lessonId      = $lessonMap[$lessonCode];
                    $detStartTime  = $this->parseTime($detData['ClassSlots::StartTime']);
                    $detEffStart   = $this->parseDate($detData['ClassSlots::StartDate']);
                    $detEffEnd     = (trim($detData['ClassSlots::EndDate']) !== '')
                        ? $this->parseDate($detData['ClassSlots::EndDate'])
                        : null;

                    // 重複判定: schedule_line_id + start_time + lesson_id + effective_start
                    $existing = ScheduleDetail::where('schedule_line_id', $lineId)
                        ->where('start_time', $detStartTime)
                        ->where('lesson_id', $lessonId)
                        ->where('effective_start', $detEffStart)
                        ->first();

                    if ($existing) {
                        $existing->update(['effective_end' => $detEffEnd]);
                        $detailUpdated++;
                    } else {
                        ScheduleDetail::create([
                            'schedule_line_id' => $lineId,
                            'start_time'       => $detStartTime,
                            'lesson_id'        => $lessonId,
                            'effective_start'  => $detEffStart,
                            'effective_end'    => $detEffEnd,
                        ]);
                        $detailCreated++;
                    }
                }
            }
        });

        return [
            'errors'         => [],
            'line_created'   => $lineCreated,
            'line_updated'   => $lineUpdated,
            'detail_created' => $detailCreated,
            'detail_updated' => $detailUpdated,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /** YYYY/M/D または YYYY-MM-DD → Y-m-d。解析不可は null */
    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // YYYY/M/D
        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        // YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /** H:i または HH:i → H:i:s。解析不可は null */
    private function parseTime(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $value, $m)) {
            return sprintf('%02d:%02d:00', (int)$m[1], (int)$m[2]);
        }

        return null;
    }

    /** Days::DayName → dow (0=日〜6=土)。空なら StartDate で算出 */
    private function toDow(string $dayName, string $startDate): int
    {
        $key = strtolower(trim($dayName));
        if (isset(self::DOW_MAP[$key])) {
            return self::DOW_MAP[$key];
        }

        $parsed = $this->parseDate($startDate);
        if ($parsed !== null) {
            return (int)Carbon::parse($parsed)->dayOfWeek;
        }

        return 0;
    }

    /** "103 - Kusatsu" → school_name。school_code で schools テーブルを検索し正式名を優先 */
    private function resolveSchoolName(string $raw, array $schoolMap): string
    {
        $raw = trim($raw);

        if (str_contains($raw, ' - ')) {
            [$code, $name] = explode(' - ', $raw, 2);
            $code = trim($code);
            $name = trim($name);

            if (isset($schoolMap[$code])) {
                return $schoolMap[$code];
            }

            return $name;
        }

        return $raw;
    }

    /** employee_code を 6 桁左ゼロ埋め（空文字はそのまま） */
    private function padEmployeeCode(string $code): string
    {
        if ($code === '') {
            return '';
        }
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
}
