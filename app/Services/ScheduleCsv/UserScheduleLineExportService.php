<?php

namespace App\Services\ScheduleCsv;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserScheduleLineExportService
{
    /**
     * CSV をストリーミングで返す（メモリ節約、巨大データOK）
     *
     * @param array{employee_code?:?string,schedule_id?:?int,date_from?:?string,date_to?:?string,active_only?:bool} $filters
     */
    public function streamCsv(array $filters, string $filename, bool $addBom = true): StreamedResponse
    {
        $headers = [
            "Content-Type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=\"$filename\"",
        ];

        return response()->streamDownload(function () use ($filters, $addBom) {
            $out = fopen('php://output', 'w');

            if ($addBom) {
                // Excel で文字化け防止
                fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            }

            // === ヘッダ（テンプレと完全一致の表示名） ===
            $header = [
                "User Name ('name')",
                "Employ Code ('user_id')",
                "Type ('label')",
                "Total Minutes ('total_minutes')",
                "Contract Start ('effective_start')",
                "Contract End ('effective_end')",
                "School ('school_name')",
                "DOW ('dow')",
                "Shift From ('start_time')",
                "Shift To ('end_time')",
                "Line Start ('effective_start')",
                "Line End ('effective_end')",
                "Start At ('start_time')",
                "Lesson Name ('lesson_code')",
                "Lesson Start ('effective_start')",
                "Lesson End ('effective_end')",
            ];
            fputcsv($out, $header);

            // === ベースクエリ（明細基点で結合） ===
            $q = DB::table('schedule_details as sd')
                ->join('schedule_lines as sl', 'sd.schedule_line_id', '=', 'sl.id')
                ->join('schedules as s', 'sl.schedule_id', '=', 's.id')
                ->join('users as u', 's.user_id', '=', 'u.id')
                ->join('lessons as l', 'sd.lesson_id', '=', 'l.id')
                ->selectRaw("
                    u.name as user_name,
                    u.employee_code as emp,
                    s.label,
                    s.total_minutes,
                    s.effective_start as sch_from,
                    s.effective_end   as sch_to,
                    sl.school_name,
                    sl.dow,
                    sl.start_time,
                    sl.end_time,
                    sl.effective_start as line_from,
                    sl.effective_end   as line_to,
                    sd.start_time      as start_at,
                    l.lesson_code,
                    sd.effective_start as det_from,
                    sd.effective_end   as det_to
                ");

            // === フィルタ ===
            if ($filters['employee_code'] ?? null) {
                // 6桁完全一致 or 前方/部分一致にも対応（数字以外混入も考慮）
                $code = preg_replace('/\D/', '', $filters['employee_code']);
                if (strlen($code) === 6) {
                    $q->where('u.employee_code', $code);
                } elseif ($code !== '') {
                    $q->where('u.employee_code', 'like', "%$code%");
                }
            }
            if ($filters['schedule_id'] ?? null) {
                $q->where('sl.schedule_id', (int)$filters['schedule_id']);
            }
            if ($filters['date_from'] ?? null) {
                // 期間と交差する detail（det_to が NULL ならオープンエンド）
                $from = $filters['date_from'];
                $q->where(function ($w) use ($from) {
                    $w->whereNull('sd.effective_end')
                        ->orWhere('sd.effective_end', '>=', $from);
                });
            }
            if ($filters['date_to'] ?? null) {
                $to = $filters['date_to'];
                $q->where('sd.effective_start', '<=', $to);
            }
            if ($filters['active_only'] ?? false) {
                $q->where('s.is_active', true);
            }

            // === 並び順（人→契約→枠→明細） ===
            $q->orderBy('u.employee_code')
                ->orderBy('s.effective_start')
                ->orderBy('s.effective_end')
                ->orderBy('sl.dow')
                ->orderBy('sl.start_time')
                ->orderBy('sd.effective_start');

            // === ストリーム書き出し（カーソルで省メモリ） ===
            foreach ($q->cursor() as $r) {
                fputcsv($out, [
                    $r->user_name,
                    $r->emp,
                    $r->label,
                    (int) $r->total_minutes,
                    $this->d($r->sch_from),
                    $this->d($r->sch_to),

                    $r->school_name,
                    $this->dowDisp((int)$r->dow),         // 1:月〜6:土, 0(日)を 7:日 に再フォーマット
                    $this->t($r->start_time),             // H:i
                    $this->t($r->end_time),               // H:i
                    $this->d($r->line_from),
                    $this->d($r->line_to),

                    $this->t($r->start_at),               // H:i
                    $r->lesson_code,
                    $this->d($r->det_from),
                    $this->d($r->det_to),
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    // === 表示フォーマット（テンプレ互換） ===

    private function d(?string $ymd): ?string
    {
        if (!$ymd) return null;
        // DB は Y-m-d を想定、念のため10桁で切る
        return substr($ymd, 0, 10);
    }

    private function t(?string $time): ?string
    {
        if (!$time) return null;
        // "HH:MM:SS" or "HH:MM" を "H:i" 化
        $parts = explode(':', $time);
        $h = (int)($parts[0] ?? 0);
        $m = (int)($parts[1] ?? 0);
        return sprintf('%02d:%02d', $h, $m);
    }

    /**
     * DBの dow(0..6, 0=日) を "1:月".."7:日" 表記に変換
     */
    private function dowDisp(int $dow): string
    {
        static $ja = [0 => '日', 1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土'];
        if ($dow === 0) return '7:日';
        return $dow . ':' . $ja[$dow];
    }
}
