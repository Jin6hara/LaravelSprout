<?php

namespace App\Services\ScheduleLine;

use App\Models\ScheduleLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleLineCopyService
{
    /**
     * @throws \RuntimeException 同一内容の行が期間内に既に存在する場合
     */
    public function copy(ScheduleLine $line, array $data): ScheduleLine
    {
        $newStart     = Carbon::parse($data['effective_start'])->startOfDay();
        $newEnd       = Carbon::parse($data['effective_end'])->startOfDay();
        $targetUserId = $data['user_id'] ?? null;

        return DB::transaction(function () use ($line, $data, $newStart, $newEnd, $targetUserId) {
            // 1) 元行クローズ（新開始日が元行の期間内に収まる場合のみ）
            $lineStart = Carbon::parse($line->effective_start)->startOfDay();
            $lineEnd   = $line->effective_end
                ? Carbon::parse($line->effective_end)->startOfDay()
                : null;

            if ($newStart->gt($lineStart) && (!$lineEnd || $lineEnd->gte($newStart))) {
                $line->effective_end = $newStart->copy()->subDay()->toDateString();
                $line->save();
            }

            // 2) 重複チェック
            $exists = ScheduleLine::query()
                ->when(
                    is_null($targetUserId),
                    fn($q) => $q->whereNull('user_id'),
                    fn($q) => $q->where('user_id', $targetUserId)
                )
                ->where('dow', $line->dow)
                ->whereEqualsInsensitive('school_name', $line->school_name)
                ->where('start_time', $line->start_time)
                ->where('end_time', $line->end_time)
                ->where(function ($q) use ($newStart, $newEnd) {
                    $q->whereDate('effective_start', '<=', $newEnd)
                      ->where(function ($qq) use ($newStart) {
                          $qq->whereDate('effective_end', '>=', $newStart)
                             ->orWhereNull('effective_end');
                      });
                })
                ->exists();

            if ($exists) {
                throw new \RuntimeException('同一内容の行が指定期間に既に存在します（重複回避ルール）。');
            }

            // 3) 新規ライン作成
            $created = $line->replicate(['id', 'effective_start', 'effective_end', 'created_at', 'updated_at']);
            $created->user_id         = $targetUserId;
            $created->effective_start = $newStart->toDateString();
            $created->effective_end   = $newEnd->toDateString();
            $created->parent_line_id  = $line->id;
            $created->handover_memo   = $data['handover_memo'] ?? null;
            $created->save();

            // 4) details をクリップして複写
            $line->loadMissing(['details']);
            foreach ($line->details as $d) {
                $dStart = Carbon::parse($d->effective_start)->startOfDay();
                $dEnd   = $d->effective_end
                    ? Carbon::parse($d->effective_end)->startOfDay()
                    : null;

                $overlapStart = $dStart->max($newStart);
                $overlapEnd   = $dEnd ? $dEnd->min($newEnd) : $newEnd;

                if ($overlapStart->gt($overlapEnd)) {
                    continue;
                }

                $newDetail = $d->replicate(['id', 'created_at', 'updated_at']);
                $newDetail->schedule_line_id = $created->id;
                $newDetail->effective_start  = $overlapStart->toDateString();
                $newDetail->effective_end    = $overlapEnd->toDateString();
                $newDetail->save();
            }

            return $created;
        });
    }
}
