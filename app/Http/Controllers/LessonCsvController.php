<?php

namespace App\Http\Controllers;

use App\Models\Lesson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LessonCsvController extends Controller
{
    private const EXPORT_HEADERS = [
        'id', 'lesson_name', 'lesson_code', 'note', 'lesson_minute',
        'lesson_type', 'ps_unique_lesson_code', 'fm_lesson_code',
        'created_at', 'updated_at',
    ];

    public function show()
    {
        return view('csv.lesson_csv');
    }

    public function export()
    {
        $lessons  = Lesson::orderBy('id')->get();
        $filename = 'lessons_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($lessons) {
            $fp = fopen('php://output', 'w');
            fputcsv($fp, self::EXPORT_HEADERS);
            foreach ($lessons as $lesson) {
                fputcsv($fp, [
                    $lesson->id,
                    $lesson->lesson_name,
                    $lesson->lesson_code,
                    $lesson->note,
                    $lesson->lesson_minute,
                    $lesson->lesson_type,
                    $lesson->ps_unique_lesson_code,
                    $lesson->fm_lesson_code,
                    $lesson->created_at,
                    $lesson->updated_at,
                ]);
            }
            fclose($fp);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $path = $request->file('csv_file')->getRealPath();
        $fp   = fopen($path, 'r');
        if (!$fp) {
            return back()->withErrors(['csv_file' => 'ファイルを開けません。']);
        }

        $rawHeader = fgetcsv($fp);
        if (!$rawHeader) {
            fclose($fp);
            return back()->withErrors(['csv_file' => 'ヘッダー行がありません。']);
        }

        // BOM除去 & trim
        $header = array_map(function (string $h): string {
            return trim(preg_replace('/^\xEF\xBB\xBF/', '', $h));
        }, $rawHeader);

        // 必須カラム確認
        $requiredCols = ['lesson_name', 'lesson_code', 'lesson_minute'];
        $missing = array_diff($requiredCols, $header);
        if (!empty($missing)) {
            fclose($fp);
            return back()->withErrors([
                'csv_file' => '必須カラムが不足しています: ' . implode(', ', $missing),
            ]);
        }

        $rows        = [];
        $parseErrors = [];
        $rowNum      = 1;

        while (($row = fgetcsv($fp)) !== false) {
            $rowNum++;
            if (count($row) !== count($header)) {
                $parseErrors[] = "{$rowNum}行目: 列数が一致しません（期待: " . count($header) . "、実際: " . count($row) . "）";
                continue;
            }
            $data = array_combine($header, $row);
            foreach ($data as $k => $v) {
                $data[$k] = $this->normalizeCsvValue($v);
            }
            $rows[] = ['row' => $rowNum, 'data' => $data];
        }
        fclose($fp);

        if (!empty($parseErrors)) {
            return back()->with('toast_errors', $parseErrors);
        }

        if (empty($rows)) {
            return back()->withErrors(['csv_file' => 'データ行がありません。']);
        }

        // 全行バリデーション
        $validationErrors = [];
        foreach ($rows as ['row' => $rowNum, 'data' => $data]) {
            $v = Validator::make($data, [
                'lesson_name'           => ['nullable', 'string', 'max:255'],
                'lesson_code'           => ['nullable', 'string', 'max:255'],
                'lesson_minute'         => ['nullable', 'integer', 'min:0'],
                'note'                  => ['nullable', 'string', 'max:1000'],
                'lesson_type'           => ['nullable', 'string', 'max:255'],
                'ps_unique_lesson_code' => ['nullable', 'string', 'max:255'],
                'fm_lesson_code'        => ['nullable', 'string', 'max:255'],
            ]);

            if ($v->fails()) {
                foreach ($v->errors()->all() as $msg) {
                    $validationErrors[] = "{$rowNum}行目: {$msg}";
                }
            }
        }

        if (!empty($validationErrors)) {
            return back()->with('toast_errors', $validationErrors);
        }

        // DB保存（トランザクション）
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            foreach ($rows as ['data' => $data]) {
                $id = (isset($data['id']) && is_numeric($data['id']))
                    ? (int) $data['id']
                    : null;

                $payload = [
                    'lesson_name'           => $data['lesson_name'],
                    'lesson_code'           => $data['lesson_code'],
                    'note'                  => $data['note'] ?? null,
                    'lesson_minute'         => (int) $data['lesson_minute'],
                    'lesson_type'           => $data['lesson_type'] ?? null,
                    'ps_unique_lesson_code' => $data['ps_unique_lesson_code'] ?? null,
                    'fm_lesson_code'        => $data['fm_lesson_code'] ?? null,
                ];

                if ($id !== null) {
                    $lesson = Lesson::find($id);
                    if ($lesson) {
                        $lesson->update($payload);
                        $updated++;
                    } else {
                        Lesson::create($payload);
                        $created++;
                    }
                } else {
                    Lesson::create($payload);
                    $created++;
                }
            }
        });

        return back()->with('toast', "インポート完了：作成 {$created} 件、更新 {$updated} 件");
    }

    /**
     * 空白文字・空文字列を null に正規化する。
     */
    private function normalizeCsvValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }
}
