<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScheduleDetail\BulkUpdateScheduleDetailRequest;
use App\Models\Lesson;
use App\Models\ScheduleLine;
use App\Models\ScheduleDetail;
use App\Services\CurrentScopeService;
use App\Services\ScheduleDetail\BulkUpdateScheduleDetailService;
use App\Services\ScheduleDetail\CopyScheduleDetailService;
use App\Services\ScheduleDetail\StoreBlankScheduleDetailService;
use Illuminate\Support\Facades\Validator;

class ScheduleDetailController extends Controller
{
    public function __construct(
        private CurrentScopeService $scopeService,
        private StoreBlankScheduleDetailService $storeService,
        private CopyScheduleDetailService $copyService,
        private BulkUpdateScheduleDetailService $bulkUpdateService,
    ) {}

    /**
     * スケジュール詳細編集画面
     * GET /schedule/{line}/details — ScheduleLine に紐づく ScheduleDetail を一覧表示し編集・削除できる
     */
    public function edit(ScheduleLine $line)
    {
        $this->authorize('view', $line);

        // スコープ検証：lineのdistrict/departmentが現在のスコープと一致しなければ一覧へ戻す
        $districtId   = $this->scopeService->currentDistrictId();
        $departmentId = $this->scopeService->currentDepartmentId();
        if (($districtId !== null && $line->district_id !== $districtId) ||
            ($departmentId !== null && $line->department_id !== $departmentId)) {
            return redirect('/schedule_manager')
                ->with('toast', '所属が変更されたため、一覧に戻りました。');
        }
        $details = ScheduleDetail::query()
            ->with([
                'lesson:id,lesson_name,lesson_code,note,lesson_minute,lesson_type',
            ])
            ->where('schedule_line_id', $line->id)
            ->orderBy('start_time')
            ->orderBy('schedule_details.effective_start')
            ->get();

        // レッスン一覧（選択肢用）
        $lessonOptions = Lesson::query()
            ->orderBy('lesson_code')
            ->get(['id', 'lesson_code', 'lesson_name', 'ps_unique_lesson_code']);

        // ヘッダー表示用の値を用意

        $dowOptions = [
            0 => '日',
            1 => '月',
            2 => '火',
            3 => '水',
            4 => '木',
            5 => '金',
            6 => '土',
        ];

        $line->loadMissing('user');
        $chips = collect([$line->user])->filter();

        $lineStart = optional($line->effective_start)->toDateString();
        $lineEnd   = optional($line->effective_end)->toDateString();

        return view('schedule.detailsEdit', [
            'line'    => $line,
            'details' => $details,
            'lessonOptions' => $lessonOptions,
            'dowOptions' => $dowOptions,
            'chips'     => $chips,
            'lineStart' => $lineStart,
            'lineEnd'   => $lineEnd,
        ]);
    }

    /**
     * 空の新規 ScheduleDetail を追加（JSON API）
     * POST /schedule/{line}/details — 既存の最初の Lesson を使って 00:00 の空明細を生成
     */
    public function store(ScheduleLine $line)
    {
        $this->authorize('update', $line);
        if (!Lesson::query()->orderBy('id')->exists()) {
            $this->authorize('create', Lesson::class);
        }

        try {
            $detail = $this->storeService->handle($line);
            $detail->loadMissing('lesson');
            return response()->json([
                'ok' => true,
                'message' => sprintf("Blank detail added (00:00, %s).", optional($detail->lesson)->lesson_code ?? 'TEMP'),
                'new_id' => $detail->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok' => false,
                'message' => 'Failed to add blank detail.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * ScheduleDetail を複製（JSON API）
     * POST /schedule/details/{detail}/copy — ユニーク制約に抵触しないよう effective_start をずらして複写
     */
    public function copy(ScheduleDetail $detail)
    {
        $this->authorize('copy', $detail);

        try {
            $created = $this->copyService->handle($detail);
            $startDisplay = $detail->start_hm;
            $lessonCode   = optional($detail->lesson)->lesson_code ?? '-';
            return response()->json([
                'ok' => true,
                'message' => sprintf("Detail copied (%s %s).", $startDisplay, $lessonCode),
                'new_id' => $created->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok' => false,
                'message' => 'Failed to copy detail.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * ScheduleDetail を一括保存（JSON API）
     * POST /schedule/{line}/details/bulk-update — lesson_code で Lesson を解決し、明細の時刻・期間・メモを更新
     */
    public function bulkUpdate(BulkUpdateScheduleDetailRequest $request, ScheduleLine $line)
    {
        $this->authorize('update', $line);

        $items = $request->input('items', []);
        $errors = [];
        $resolvedItems = [];

        foreach ($items as $raw) {
            $detailId = $raw['id'] ?? null;
            if (!$detailId) {
                $errors[] = ['id' => null, 'messages' => ['Detail ID is missing.']];
                continue;
            }

            /** @var ScheduleDetail|null $detail */
            $detail = ScheduleDetail::query()
                ->where('schedule_line_id', $line->id)
                ->find($detailId);

            if (!$detail) {
                $errors[] = ['id' => $detailId, 'messages' => ['Detail not found.']];
                continue;
            }
            $this->authorize('update', $detail);

            $v = Validator::make($raw, BulkUpdateScheduleDetailRequest::itemRules());
            if ($v->fails()) {
                $errors[] = ['id' => $detailId, 'messages' => $v->errors()->all()];
                continue;
            }
            $data = $v->validated();

            $lesson = Lesson::query()
                ->where(function ($q) use ($data) {
                    $q->where('lesson_code', $data['lesson_code'])
                      ->orWhere('ps_unique_lesson_code', $data['lesson_code']);
                })
                ->first();
            if (!$lesson) {
                $errors[] = ['id' => $detailId, 'messages' => ['lesson_code / ps_unique_lesson_code not found.']];
                continue;
            }
            $this->authorize('update', $lesson);

            $resolvedItems[] = compact('detail', 'lesson', 'data');
        }

        try {
            $updated = $this->bulkUpdateService->handle($resolvedItems);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok' => false,
                'message' => 'Failed to save details.',
                'error' => $e->getMessage(),
            ], 422);
        }

        $msg = "Saved {$updated} detail(s).";
        if (!empty($errors)) $msg .= " " . count($errors) . " error(s) occurred.";

        return response()->json([
            'ok'      => empty($errors),
            'message' => $msg,
            'updated' => $updated,
            'errors'  => $errors,
        ], empty($errors) ? 200 : 207);
    }

    /**
     * ScheduleDetail を削除（JSON API）
     * DELETE /schedule/details/{detail} — 1件の明細を削除し、削除した時刻・コードを含むメッセージを返す
     */
    public function destroy(ScheduleDetail $detail)
    {
        $this->authorize('delete', $detail);

        try {
            $startDisplay = $detail->start_hm;
            $lessonCode   = optional($detail->lesson)->lesson_code ?? '-';
            $detail->delete();
            return response()->json([
                'ok' => true,
                'message' => sprintf("Detail deleted (%s %s).", $startDisplay, $lessonCode),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok' => false,
                'message' => 'Failed to delete detail.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * lesson_code または ps_unique_lesson_code でレッスン情報を取得（JSON API）
     * GET /schedule/lessons/{code} — 入力補完・自動補填に使用
     */
    public function findLessonByCode(string $code)
    {
        $this->authorize('viewAny', Lesson::class);

        $lesson = Lesson::query()
            ->select(['id', 'lesson_name', 'lesson_code', 'ps_unique_lesson_code', 'note', 'lesson_minute'])
            ->where(function ($q) use ($code) {
                $q->where('lesson_code', $code)
                  ->orWhere('ps_unique_lesson_code', $code);
            })
            ->first();

        if (!$lesson) {
            return response()->json(['ok' => false, 'message' => 'Lesson not found.'], 404);
        }
        return response()->json([
            'ok' => true,
            'lesson' => $lesson,
        ]);
    }
}
