<?php

namespace App\Services\ScheduleCsv;

use App\Models\User;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UserScheduleCsvImportService
{
    /**
     * CSVヘッダ期待:
     * - user_id (6桁 employee_code) もしくは employee_code
     * - label
     * - total_minutes
     * - effective_start (YYYY-MM-DD)
     * - effective_end   (YYYY-MM-DD)
     *
     * @return array{0: array, 1: array} [summary, errors]
     */
    public function import(string $csvPath, bool $doUpdate = false): array
    {
        $fp = fopen($csvPath, 'r');
        if (!$fp) {
            return [['created' => 0, 'updated' => 0, 'skipped' => 0, 'missing_user' => 0, 'invalid' => 0], ['ファイルを開けませんでした']];
        }

        $header = fgetcsv($fp);
        if (!$header) {
            return [['created' => 0, 'updated' => 0, 'skipped' => 0, 'missing_user' => 0, 'invalid' => 0], ['ヘッダ行が読めません']];
        }

        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'missing_user' => 0, 'invalid' => 0];
        $errors  = [];

        DB::transaction(function () use ($fp, $header, $doUpdate, &$summary, &$errors) {
            while ($row = fgetcsv($fp)) {
                $raw = @array_combine($header, $row);
                if ($raw === false) {
                    $summary['invalid']++;
                    $errors[] = '列数不一致の行をスキップ';
                    continue;
                }

                // 別名ヘッダ対応（user_id列にemployee_codeが入ってくる仕様も許容）
                $emp = $raw['employee_code'] ?? $raw['user_id'] ?? null;
                $label = $raw['label'] ?? null;
                $total = $raw['total_minutes'] ?? null;
                $from  = $raw['effective_start'] ?? null;
                $to    = $raw['effective_end'] ?? null;

                // 正規化
                $emp = $this->normalizeEmployeeCode($emp);
                $label = $label !== null ? trim((string)$label) : null;
                $totalMinutes = $this->toIntOrNull($total);
                $fromDate = $this->toDate($from);
                $toDate   = $this->toDate($to);

                // バリデーション
                if (!$emp || !$fromDate || !$toDate) {
                    $summary['invalid']++;
                    $errors[] = "必須不足(emp/from/to) → " . json_encode($raw, JSON_UNESCAPED_UNICODE);
                    continue;
                }
                if ($fromDate > $toDate) {
                    $summary['invalid']++;
                    $errors[] = "期間逆転(from>to) → " . json_encode($raw, JSON_UNESCAPED_UNICODE);
                    continue;
                }

                $user = User::where('employee_code', $emp)->first();
                if (!$user) {
                    $summary['missing_user']++;
                    $errors[] = "ユーザー未発見 employee_code={$emp}";
                    continue;
                }

                // 一意キーの考え方：
                //   ユーザー + 期間 + label(任意) をキーとみなす。
                //   labelが空のときは null で一致。
                $where = [
                    'user_id'         => $user->id,
                    'effective_start' => $fromDate,
                    'effective_end'   => $toDate,
                    'label'           => $label,
                ];

                if ($doUpdate) {
                    // 既存あれば更新、なければ作成
                    $schedule = Schedule::updateOrCreate(
                        $where,
                        [
                            'total_minutes' => $totalMinutes ?? 0,
                            'is_active'     => true,
                        ]
                    );
                    if ($schedule->wasRecentlyCreated) {
                        $summary['created']++;
                    } else {
                        $summary['updated']++;
                    }
                } else {
                    // 既存があればスキップ、なければ作成
                    $schedule = Schedule::firstOrCreate(
                        $where,
                        [
                            'total_minutes' => $totalMinutes ?? 0,
                            'is_active'     => true,
                        ]
                    );
                    if ($schedule->wasRecentlyCreated) {
                        $summary['created']++;
                    } else {
                        $summary['skipped']++;
                    }
                }
            }
        });

        return [$summary, $errors];
    }

    private function normalizeEmployeeCode($v): ?string
    {
        if ($v === null || $v === '') return null;
        $s = preg_replace('/\D/', '', (string)$v); // 数字のみ
        if ($s === '') return null;
        // 左ゼロ詰めで6桁に
        return str_pad($s, 6, '0', STR_PAD_LEFT);
    }

    private function toIntOrNull($v): ?int
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (int)$v;
        return null;
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
}
