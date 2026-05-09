<?php

namespace App\Services\ScheduleCsv;

use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserScheduleLineExportService
{
    /**
     * CSV をストリーミングで返す（メモリ節約、巨大データOK）
     *
     * @param array{employee_code?:?string,user_id?:?int,date_from?:?string,date_to?:?string} $filters
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
                fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            }

            // === ヘッダ ===
            $header = [
                "User Name ('name')",
                "Employ Code ('user_id')",
                "Total Minutes ('total_minutes')",
                "Line Start ('effective_start')",
                "Line End ('effective_end')",
                "School ('school_name')",
                "DOW ('dow')",
                "Shift From ('start_time')",
                "Shift To ('end_time')",
                "Start At ('start_time')",
                "Lesson Name ('lesson_code')",
                "Lesson Start ('effective_start')",
                "Lesson End ('effective_end')",
            ];
            fputcsv($out, $header);

            // === ベースクエリ（schedule_lines → users 直結） ===
            $q = DB::table('schedule_details as sd')
                ->join('schedule_lines as sl', 'sd.schedule_line_id', '=', 'sl.id')
                ->join('users as u', 'sl.user_id', '=', 'u.id')
                ->join('lessons as l', 'sd.lesson_id', '=', 'l.id')
                ->selectRaw("
                    u.name as user_name,
                    u.employee_code as emp,
                    sl.total_minutes,
                    sl.effective_start as line_from,
                    sl.effective_end   as line_to,
                    sl.school_name,
                    sl.dow,
                    sl.start_time,
                    sl.end_time,
                    sd.start_time      as start_at,
                    l.lesson_code,
                    sd.effective_start as det_from,
                    sd.effective_end   as det_to
                ");

            // === フィルタ ===
            if ($filters['employee_code'] ?? null) {
                $code = preg_replace('/\D/', '', $filters['employee_code']);
                if (strlen($code) === 6) {
                    $q->where('u.employee_code', $code);
                } elseif ($code !== '') {
                    $q->where('u.employee_code', 'like', "%$code%");
                }
            }
            if ($filters['user_id'] ?? null) {
                $q->where('sl.user_id', (int)$filters['user_id']);
            }
            if ($filters['date_from'] ?? null) {
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

            // === 並び順 ===
            $q->orderBy('u.employee_code')
                ->orderBy('sl.effective_start')
                ->orderBy('sl.effective_end')
                ->orderBy('sl.dow')
                ->orderBy('sl.start_time')
                ->orderBy('sd.effective_start');

            // === ストリーム書き出し ===
            foreach ($q->cursor() as $r) {
                fputcsv($out, [
                    $r->user_name,
                    $r->emp,
                    (int) $r->total_minutes,
                    $this->d($r->line_from),
                    $this->d($r->line_to),
                    $r->school_name,
                    $this->dowDisp((int)$r->dow),
                    $this->t($r->start_time),
                    $this->t($r->end_time),
                    $this->t($r->start_at),
                    $r->lesson_code,
                    $this->d($r->det_from),
                    $this->d($r->det_to),
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    private function d(?string $ymd): ?string
    {
        if (!$ymd) return null;
        return substr($ymd, 0, 10);
    }

    private function t(?string $time): ?string
    {
        if (!$time) return null;
        $parts = explode(':', $time);
        $h = (int)($parts[0] ?? 0);
        $m = (int)($parts[1] ?? 0);
        return sprintf('%02d:%02d', $h, $m);
    }

    private function dowDisp(int $dow): string
    {
        static $ja = [0 => '日', 1 => '月', 2 => '火', 3 => '水', 4 => '木', 5 => '金', 6 => '土'];
        if ($dow === 0) return '7:日';
        return $dow . ':' . $ja[$dow];
    }
}
