<?php

namespace App\Services\Calendar\Providers;

use App\Models\Sub;
use App\Models\User;
use App\Services\Calendar\{CandidateEvent, EventType, PlanGroup};
use App\Services\Calendar\Contracts\CalendarEventProvider;
use Carbon\Carbon;

class SubProvider implements CalendarEventProvider
{
    public function provide(User $user, Carbon $start, Carbon $end): array
    {
        $rows = Sub::query()
            ->where('user_id', $user->id)
            ->whereBetween('sub_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('sub_date')->orderBy('start_time')
            ->get();

        $events = [];
        foreach ($rows as $s) {
            $ymd     = $s->sub_date->toDateString();
            $startHm = $this->hm($s->start_time); // 'H:i' or null
            $endHm   = $this->hm($s->end_time);   // 'H:i' or null

            // allDay: どちらか無いなら終日扱い（EventProviderと同じ判定）
            $isAllDay = (is_null($startHm) || is_null($endHm));

            $startAt = $isAllDay
                ? $ymd
                : Carbon::parse("{$ymd} {$startHm}")->toIso8601String();

            $endAt = $isAllDay
                ? Carbon::parse($ymd)->addDay()->toDateString() // FullCalendar の allDay 仕様
                : Carbon::parse("{$ymd} {$endHm}")->toIso8601String();

            // タイトル（時間＋note。EventProviderの生成方針に寄せて短く）
            $title = $this->makeDisplayTitle($startHm, $endHm, $s->note);

            $classNames = ['fc-regular-shift-sub'];

            $events[] = new CandidateEvent([
                'title'      => $title,
                'start'      => $startAt,
                'end'        => $endAt,
                'allDay'     => $isAllDay,
                'display'    => 'auto',
                'classNames' => $classNames,
                'extendedProps' => [
                    'category'      => 'sub',
                    'note'          => $s->note,
                    'total_minutes' => (int)($s->total_duration ?? 0),
                    'total_hm'      => $this->toHm((int)($s->total_duration ?? 0)),
                    'sort_order'    => 10, // 必要なら並び順を微調整
                ],
                // Resolver で上書きされるが、デフォルトも入れておく（EventProviderと同様）
                'level'     => 3,
                'type'      => EventType::ON,
                'planGroup' => PlanGroup::EVENT,
                'dateKey'   => $ymd,
            ]);
        }

        return $events;
    }

    /** TIME(CARBON|string|null) を 'H:i' へ正規化 */
    private function hm($time): ?string
    {
        if (is_null($time)) return null;
        if ($time instanceof \DateTimeInterface) {
            return Carbon::instance($time)->format('H:i');
        }
        // 'H:i' or 'H:i:s' など文字列時
        $str = trim((string)$time);
        if ($str === '') return null;
        // すでに H:i ならそのまま、H:i:s なら先頭5文字
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $str)) {
            return substr($str, 0, 5);
        }
        // 最後の保険：Carbonで解釈できたらH:i化
        try {
            return Carbon::parse($str)->format('H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function toHm(int $m): string
    {
        $h = intdiv($m, 60);
        $r = $m % 60;
        return sprintf('%d:%02d', $h, $r);
    }

    private function makeDisplayTitle(?string $startHm, ?string $endHm, ?string $note): string
    {
        $time = ($startHm && $endHm) ? "{$startHm}–{$endHm}" : null;

        if ($time && $note) return "Sub {$time} — {$note}";
        if ($time)          return "Sub {$time}";
        if ($note)          return "Sub — {$note}";
        return 'Sub';
    }
}
