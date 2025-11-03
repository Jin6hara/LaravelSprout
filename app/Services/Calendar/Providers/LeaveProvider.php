<?php

namespace App\Services\Calendar\Providers;

use App\Models\Leave;
use App\Models\User;
use App\Services\Calendar\EventType;
use App\Services\Calendar\PlanGroup;
use Carbon\Carbon;
use Carbon\CarbonInterface; // ★added(11/3)
use Illuminate\Support\Collection;

class LeaveProvider
{
    public function __construct(
        private readonly array $meta = [
            'level' => 1, // ベース250831FC指示
            'type'  => EventType::OFF,
            'plan'  => PlanGroup::EVENT,
        ]
    ) {}

    /**
     * @return \Illuminate\Support\Collection<int, array> FullCalendar event[]
     */
    public function provide(User $user, Carbon $start, Carbon $end): Collection
    {
        $leaves = Leave::query()
            ->where('user_id', $user->id)
            ->approved()
            ->between($start, $end)
            ->get();

        $events = collect();

        foreach ($leaves as $leave) {
            foreach ($leave->eachDate() as $date) {
                // allDay のときは end は翌日（FullCalendar仕様）
                $isAllDay = $leave->isAllDay();
                $startAt  = $isAllDay
                    ? $date->toDateString()
                    // ★changed(11/3): 文字列連結 → 安全な合成に変更
                    : $this->mergeDateAndTime($date, $leave->time_start)->toIso8601String();

                $endAt    = $isAllDay
                    ? $date->copy()->addDay()->toDateString()
                    // ★changed(11/3): 文字列連結 → 安全な合成に変更
                    : $this->mergeDateAndTime($date, $leave->time_end)->toIso8601String();

                $classes = ['fc-leave', "fc-leave-{$leave->kind}"];
                if ($leave->excused !== 'unknown') $classes[] = "fc-leave-{$leave->excused}";

                $events->push([
                    'title'   => $leave->displayTitle(),
                    'start'   => $startAt,
                    'end'     => $endAt,
                    'allDay'  => $isAllDay,
                    'display' => 'auto',
                    'classNames' => $classes,
                    'extendedProps' => [
                        'category' => 'leave',
                        'plan'     => $this->meta['plan'],
                        'type'     => $this->meta['type'],
                        'level'    => $this->meta['level'],
                        'sort_order' => 0, // eventOrder用
                        'leave'    => [
                            'id'           => $leave->id,
                            'kind'         => $leave->kind,
                            'excused'      => $leave->excused,
                            'special_type' => $leave->special_type,
                            'reason'       => $leave->reason,
                        ],
                    ],
                ]);
            }
        }

        return $events;
    }

    // ★added(11/3): '日付' と '時刻' を安全に合成するユーティリティ
    private function mergeDateAndTime(Carbon $date, mixed $time): Carbon
    {
        // time が Carbon（datetimeキャスト）でも 'H:i' / 'H:i:s' 文字列でもOK
        if ($time instanceof CarbonInterface) {
            return Carbon::create(
                $date->year,
                $date->month,
                $date->day,
                $time->hour,
                $time->minute,
                $time->second ?? 0,
                config('app.timezone')
            );
        }

        $v = (string) $time;

        // もし誤って 'YYYY-MM-DD H:i[:s]' が来ても時刻だけ切り出す
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}(:\d{2})?$/', $v)) {
            $v = substr($v, 11); // '2025-11-03 19:35:00' → '19:35:00'
        }

        // 'H:i' or 'H:i:s' として解釈
        $fmt = (strlen($v) === 5) ? 'H:i' : 'H:i:s';
        $t = Carbon::createFromFormat($fmt, $v, config('app.timezone'));

        return Carbon::create(
            $date->year,
            $date->month,
            $date->day,
            (int) $t->format('H'),
            (int) $t->format('i'),
            (int) $t->format('s'),
            config('app.timezone')
        );
    }
}
