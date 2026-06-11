<?php

/**
 * 有給休暇の残日数管理（消化・返戻・年度付与）を担うサービス。
 */
namespace App\Services;

use App\Enums\LeaveCreditTransactionType;
use App\Models\Leave;
use App\Models\LeaveCredit;
use App\Models\Holiday;
use App\Support\Fiscal;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LeaveBalanceService
{
    /** 承認時に消化 */
    public function consume(Leave $leave): void
    {
        $days = $this->calcDays($leave);
        if ($days <= 0) return;

        DB::transaction(function () use ($leave, $days) {
            $fy = Fiscal::fyKey(Carbon::parse($leave->start_date));
            // 悪競合防止でロック
            $credit = LeaveCredit::where('user_id', $leave->user_id)
                ->where('fy', $fy)
                ->lockForUpdate()
                ->first();

            // 無ければ0スタートで作る（事前付与が理想だが運用を止めない）
            if (!$credit) {
                $credit = LeaveCredit::create([
                    'user_id' => $leave->user_id,
                    'fy' => $fy,
                    'granted_days' => 0,
                    'used_days' => 0,
                ]);
            }

            // used を進める（マイナスにはしない）
            $credit->used_days = max(0, (float)$credit->used_days + $days);
            $credit->save();

            $credit->transactions()->create([
                'type'   => LeaveCreditTransactionType::Consume->value,
                'days'   => $days,
                'reason' => "leave#{$leave->id}",
            ]);
        });
    }

    /** 取消時に戻す（承認後→取消/削除など） */
    public function revert(Leave $leave): void
    {
        $days = $this->calcDays($leave);
        if ($days <= 0) return;

        DB::transaction(function () use ($leave, $days) {
            $fy = Fiscal::fyKey(Carbon::parse($leave->start_date));
            $credit = LeaveCredit::where('user_id', $leave->user_id)
                ->where('fy', $fy)
                ->lockForUpdate()
                ->first();

            if (!$credit) return; // 何もせず

            $credit->used_days = max(0, (float)$credit->used_days - $days);
            $credit->save();

            $credit->transactions()->create([
                'type'   => LeaveCreditTransactionType::Revert->value,
                'days'   => $days,
                'reason' => "leave#{$leave->id} cancelled",
            ]);
        });
    }

    /** 付与（年度初めなどの一括付与で使用） */
    public function grantDays(int $userId, Carbon $dateInFy, float $days, string $reason = 'annual grant'): void
    {
        DB::transaction(function () use ($userId, $dateInFy, $days, $reason) {
            $fy = Fiscal::fyKey($dateInFy);
            $credit = LeaveCredit::firstOrCreate(
                ['user_id' => $userId, 'fy' => $fy],
                ['granted_days' => 0, 'used_days' => 0]
            );
            $credit->increment('granted_days', $days);
            $credit->transactions()->create([
                'type' => LeaveCreditTransactionType::Grant->value,
                'days' => $days,
                'reason' => $reason,
            ]);
        });
    }

    /** 基本の消化日数ルール（平日カウント・祝日除外） */
    // app/Services/LeaveBalanceService.php

    private function calcDays(Leave $leave): float
    {
        $start = \Carbon\Carbon::parse($leave->start_date);
        $end   = $leave->end_date ? \Carbon\Carbon::parse($leave->end_date) : \Carbon\Carbon::parse($leave->start_date);

        // 祝日を取得（列名の違いを吸収）
        // 取り得る候補: target_date / date / holiday_date / day / ymd
        $holidays = \App\Models\Holiday::between($start->toDateString(), $end->toDateString())
            ->get();

        $holidayDates = $holidays
            ->map(function ($h) {
                $raw = $h->target_date
                    ?? $h->date
                    ?? $h->holiday_date
                    ?? $h->day
                    ?? $h->ymd
                    ?? null;
                if (!$raw) return null;
                try {
                    return \Carbon\Carbon::parse($raw)->toDateString(); // 正規化
                } catch (\Throwable $e) {
                    return null;
                }
            })
            ->filter()
            ->unique()
            ->values()
            ->flip(); // set化（date => index）

        $period = \Carbon\CarbonPeriod::create($start, $end);
        $count = 0;

        foreach ($period as $d) {
            //$isWeekend = in_array($d->dayOfWeekIso, [6, 7], true); // 6=Sat, 7=Sun
            $isHoliday = $holidayDates->has($d->toDateString());
            if (!$isHoliday) $count++; // !$isWeekend && 
        }
        
        return (float)$count;
    }
}
