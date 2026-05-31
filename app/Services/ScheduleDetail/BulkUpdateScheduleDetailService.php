<?php

namespace App\Services\ScheduleDetail;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BulkUpdateScheduleDetailService
{
    /**
     * @param  array<array{detail: \App\Models\ScheduleDetail, lesson: \App\Models\Lesson, data: array}> $resolvedItems
     */
    public function handle(array $resolvedItems): int
    {
        $updated = 0;

        DB::transaction(function () use ($resolvedItems, &$updated) {
            foreach ($resolvedItems as ['detail' => $detail, 'lesson' => $lesson, 'data' => $data]) {
                $detail->lesson_id       = $lesson->id;
                $detail->start_time      = $data['start_time'] . ':00';
                $detail->effective_start = Carbon::parse($data['effective_start'])->toDateString();
                $detail->effective_end   = isset($data['effective_end']) && $data['effective_end'] !== ''
                    ? Carbon::parse($data['effective_end'])->toDateString()
                    : null;
                if (array_key_exists('detail_note', $data)) {
                    $detail->note = $data['detail_note'] ?? null;
                }
                $detail->save();

                if (array_key_exists('note', $data)) {
                    $lesson->note = $data['note'] ?? null;
                    $lesson->save();
                }

                $updated++;
            }
        });

        return $updated;
    }
}
