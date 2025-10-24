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
use App\Support\TimeString;

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
            ->orderBy('schedule_details.effective_start')
            ->get();

        // ★ 追加: ヘッダー表示用の関連と値を用意
        $line->loadMissing(['schedule.user']); // schedule 所有ユーザー

        $dowOptions = [
            0 => '日',
            1 => '月',
            2 => '火',
            3 => '水',
            4 => '木',
            5 => '金',
            6 => '土',
        ];

        // chips 相当（0 or 1件想定）
        $chips = collect($line->schedule && $line->schedule->user ? [$line->schedule->user] : []);

        $lineStart = optional($line->effective_start)->toDateString();
        $lineEnd   = optional($line->effective_end)->toDateString();

        return view('schedule.detailsEdit', [
            'line'    => $line,
            'details' => $details,
            'dowOptions' => $dowOptions,
            'chips'     => $chips,
            'lineStart' => $lineStart,
            'lineEnd'   => $lineEnd,
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

    public function storeBlank(Request $request, ScheduleLine $line)
    {
        DB::beginTransaction();
        try {
            // start_time: 既存の最小 or 00:00 を作成
            $start = LessonStartTime::query()->orderBy('start_time')->first();
            if (!$start) {
                $start = LessonStartTime::create(['start_time' => '00:00:00']);
            }

            // lesson: 既存の先頭 or プレースホルダ作成
            $lesson = Lesson::query()->orderBy('id')->first();
            if (!$lesson) {
                $lesson = Lesson::create([
                    'lesson_name'   => '未設定',
                    'lesson_code'   => 'TEMP',
                    'note'          => null,
                    'lesson_minute' => 0,
                    'lesson_type'   => 'other',
                ]);
            }

            $detail = ScheduleDetail::create([
                'schedule_line_id'     => $line->id,
                'lesson_start_time_id' => $start->id,
                'lesson_id'            => $lesson->id,
                'effective_start'      => Carbon::today()->toDateString(),
                'effective_end'        => null,
            ]);

            DB::commit();
            $startDisplay = TimeString::normalizeToHm($start->start_time);
            return response()->json([
                'ok' => true,
                'message' => sprintf("空白明細を追加しました（%s %s）。", $startDisplay, $lesson->lesson_code),
                'new_id' => $detail->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'ok' => false,
                'message' => '空白の追加に失敗しました。',
                'error' => $e->getMessage(),
            ], 422);
        }
    }


    public function copy(Request $request, ScheduleDetail $detail)
    {
        DB::beginTransaction();
        try {
            $created = $detail->replicate(['id', 'created_at', 'updated_at']);

            // ユニーク制約を避けるために日付をずらす（必要なら）
            $baseStart = Carbon::parse($detail->effective_start);
            $baseEnd   = $detail->effective_end ? Carbon::parse($detail->effective_end) : null;

            $delta = 0;
            do {
                $candidateStart = $baseStart->copy()->addDays($delta)->toDateString();
                $exists = ScheduleDetail::query()
                    ->where('schedule_line_id',     $detail->schedule_line_id)
                    ->where('lesson_start_time_id', $detail->lesson_start_time_id)
                    ->where('lesson_id',            $detail->lesson_id)
                    ->whereDate('effective_start',  $candidateStart)
                    ->exists();

                if (!$exists) {
                    $created->effective_start = $candidateStart;
                    $created->effective_end = $baseEnd ? $baseEnd->copy()->addDays($delta)->toDateString() : null;
                    break;
                }
                $delta++;
            } while ($delta < 3650);

            if (!isset($created->effective_start)) {
                throw new \RuntimeException('複写先の日付を確保できませんでした。');
            }

            $created->save();

            // start_timeとlesson_codeを取得

            $lessonCode = optional($detail->lesson)->lesson_code ?? '-';

            DB::commit();
            $detail->loadMissing(['start', 'lesson']);
            $startDisplay = $detail->start_hm ?? TimeString::normalizeToHm(optional($detail->start)->start_time);
            $lessonCode   = optional($detail->lesson)->lesson_code ?? '-';
            return response()->json([
                'ok' => true,
                'message' => sprintf("明細を複写しました（%s %s）。", $startDisplay, $lessonCode),
                'new_id' => $created->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json([
                'ok' => false,
                'message' => '複写に失敗しました。',
                'error' => $e->getMessage(),
            ], 422);
        }
    }


    public function destroy(Request $request, ScheduleDetail $detail)
    {
        try {

            $lessonCode = optional($detail->lesson)->lesson_code ?? '-';

            $detail->delete();
            $detail->loadMissing(['start', 'lesson']);
            $startDisplay = $detail->start_hm ?? TimeString::normalizeToHm(optional($detail->start)->start_time);
            $lessonCode   = optional($detail->lesson)->lesson_code ?? '-';
            $detail->delete();
            return response()->json([
                'ok' => true,
                'message' => sprintf("明細を削除しました（%s %s）。", $startDisplay, $lessonCode),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok' => false,
                'message' => '削除に失敗しました。',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
