<?php

namespace App\Http\Controllers;

use App\Models\ScheduleLine;
use App\Models\ScheduleDetail;
use App\Models\Lesson;
use App\Models\LessonStartTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ScheduleDetailController extends Controller
{
    // 詳細編集画面
    public function edit(Request $request, ScheduleLine $line)
    {
        $details = ScheduleDetail::query()
            ->with([
                'start:id,start_time',
                'lesson:id,lesson_name,lesson_code,note,lesson_minute,lesson_type',
            ])
            ->where('schedule_line_id', $line->id)
            ->orderBy('lesson_start_time_id')
            ->get();

        return view('schedule.detailsEdit', [
            'line'    => $line,
            'details' => $details,
        ]);
    }

    // 一括保存（表示されている明細のみ）
    public function bulkUpdate(Request $request, ScheduleLine $line)
    {
        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return response()->json(['ok' => false, 'message' => '更新対象がありません。'], 422);
        }

        $errors = [];
        $updated = 0;

        DB::beginTransaction();
        try {
            foreach ($items as $raw) {
                $detailId = $raw['id'] ?? null;
                if (!$detailId) {
                    $errors[] = ['id' => null, 'messages' => ['明細IDがありません']];
                    continue;
                }

                /** @var ScheduleDetail|null $detail */
                $detail = ScheduleDetail::query()
                    ->where('schedule_line_id', $line->id)
                    ->find($detailId);

                if (!$detail) {
                    $errors[] = ['id' => $detailId, 'messages' => ['対象の明細が見つかりません']];
                    continue;
                }

                $v = Validator::make($raw, [
                    'lesson_code'     => ['required', 'string', 'max:255'],
                    'note'            => ['nullable', 'string', 'max:2000'],
                    'start_time'      => ['required', 'date_format:H:i'],
                    'effective_start' => ['required', 'date'],
                    'effective_end'   => ['nullable', 'date', 'after_or_equal:effective_start'],
                ]);
                if ($v->fails()) {
                    $errors[] = ['id' => $detailId, 'messages' => $v->errors()->all()];
                    continue;
                }
                $data = $v->validated();

                // 1) レッスンを lesson_code で取得（無ければエラー）
                $lesson = Lesson::query()->where('lesson_code', $data['lesson_code'])->first();
                if (!$lesson) {
                    $errors[] = ['id' => $detailId, 'messages' => ['指定の lesson_code が見つかりません']];
                    continue;
                }

                // 2) start_time → lesson_start_times を find-or-create
                $startTimeStr = $data['start_time'] . ':00';
                $start = LessonStartTime::query()->firstOrCreate(['start_time' => $startTimeStr]);

                // 3) schedule_details を更新
                $detail->lesson_id = $lesson->id;
                $detail->lesson_start_time_id = $start->id;
                $detail->effective_start = Carbon::parse($data['effective_start'])->toDateString();
                $detail->effective_end   = isset($data['effective_end']) && $data['effective_end'] !== ''
                    ? Carbon::parse($data['effective_end'])->toDateString()
                    : null;
                $detail->save();

                // 4) lesson の note を更新（画面の「note」は lessons.note を指す）
                if (array_key_exists('note', $data)) {
                    $lesson->note = $data['note'] ?? null;
                    $lesson->save();
                }

                $updated++;
            }

            DB::commit();

            $msg = "詳細の一括保存が完了しました（{$updated} 件更新";
            if (!empty($errors)) $msg .= "・エラー " . count($errors) . " 件";
            $msg .= "）。";

            return response()->json([
                'ok'      => empty($errors),
                'message' => $msg,
                'updated' => $updated,
                'errors'  => $errors,
            ], empty($errors) ? 200 : 207);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'ok' => false,
                'message' => '一括保存に失敗しました。',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    // lesson_code → レッスン情報取得（AJAX）
    public function findLessonByCode(string $code)
    {
        $lesson = Lesson::query()
            ->select(['id', 'lesson_name', 'lesson_code', 'note', 'lesson_minute'])
            ->where('lesson_code', $code)
            ->first();

        if (!$lesson) {
            return response()->json(['ok' => false, 'message' => 'lesson_code が見つかりません'], 404);
        }
        return response()->json([
            'ok' => true,
            'lesson' => $lesson,
        ]);
    }
}
