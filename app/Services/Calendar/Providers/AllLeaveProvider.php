<?php

namespace App\Services\Calendar\Providers;

use App\Models\Leave;
use App\Models\User;
use App\Models\Event;
use App\Services\Calendar\EventType;
use App\Services\Calendar\PlanGroup;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AllLeaveProvider
{
    public function __construct(
        private readonly array $meta = [
            'level' => 1, // ベース250831FC
            'type'  => EventType::OFF,
            'plan'  => PlanGroup::EVENT,
        ]
    ) {}

    /**
     * 指定期間内の「全ユーザー分の Leave（全ステータス）」を FullCalendar event[] 形式で返す
     *
     * @return \Illuminate\Support\Collection<int, array>
     */
    public function provide(User $viewer, Carbon $start, Carbon $end): Collection
    {
        // 全ユーザー分を対象にするため user_id フィルタは掛けない
        $leaves = Leave::query()
            // ->approved()  // ← 承認済み限定を撤去（全件取得）
            ->between($start, $end)
            ->with(['user:id,name,first_name,family_name,employee_code']) // 表示用
            ->get();

        $events = collect();

        // Eager load all relevant Event records to avoid N+1 query
        $leaveIds = $leaves->pluck('id')->all();
        $eventMap = Event::whereIn('source_leave_id', $leaveIds)
            ->pluck('id', 'source_leave_id')
            ->all();

        foreach ($leaves as $leave) {

            // どの Event に紐づくか（存在しない場合もある）
            $eventId = $eventMap[$leave->id] ?? null;

            // Leave モデル側のユーティリティを流用（LeaveProvider と同一）
            foreach ($leave->eachDate() as $date) {
                $isAllDay = $leave->isAllDay();

                $startAt = $isAllDay
                    ? $date->toDateString()
                    : Carbon::parse($date->toDateString() . ' ' . $leave->time_start)->toIso8601String();

                // FullCalendar の仕様：allDay の end は翌日
                $endAt = $isAllDay
                    ? $date->copy()->addDay()->toDateString()
                    : Carbon::parse($date->toDateString() . ' ' . $leave->time_end)->toIso8601String();

                // 表示名（ユーザー名を先頭に付ける）
                $u = $leave->user;
                $userName = $u?->name
                    ?? trim(($u?->family_name ?? '') . ' ' . ($u?->first_name ?? ''))
                    ?: 'User';
                $emp = $u?->employee_code ? "[{$u->employee_code}]" : '';
                $title = "{$userName}{$emp} - " . $leave->displayTitle();

                // CSS クラス（既存 LeaveProvider と揃える）
                $classes = ['fc-leave', "fc-leave-{$leave->kind}"];

                if ($leave->excused !== 'unknown') {
                    $classes[] = "fc-leave-{$leave->excused}";
                }

                // ✅ statusクラスを追加（例: status-approved / status-pending / status-rejected）
                if (!empty($leave->status)) {
                    $classes[] = "status-{$leave->status}";
                }

                /**
                 * ★ absence だけ例外処理（handle_type + status + excused で枠線色を決定）
                 *  1) handle_type = null && status = pending → red
                 *  2) handle_type != null && status = pending → yellow
                 */
                if ($leave->kind === 'absence') {
                    $handled = !empty($leave->handle_type);

                    if (!$handled && $leave->status === 'pending') {
                        // 1) 未ハンドル & pending
                        $classes[] = 'absence-unhandled-pending';
                    } elseif ($handled && $leave->status === 'pending') {
                        // 2) ハンドル済み & pending
                        $classes[] = 'absence-handled-pending';
                    }
                }


                $events->push([
                    'id'         => (string) ($leave->id . '-' . $date->toDateString()), // 日別ユニーク
                    'title'      => $title,
                    'start'      => $startAt,
                    'end'        => $endAt,
                    'allDay'     => $isAllDay,
                    'display'    => 'auto',
                    'classNames' => $classes,
                    'extendedProps' => [
                        'category'   => 'leave',
                        'plan'       => $this->meta['plan'],
                        'type'       => $this->meta['type'],
                        'level'      => $this->meta['level'],
                        'sort_order' => 0, // eventOrder用
                        'user' => [
                            'id'             => $u?->id,
                            'name'           => $userName,
                            'employee_code'  => $u?->employee_code,
                        ],
                        'leave' => [
                            'id'           => $leave->id,
                            'kind'         => $leave->kind,
                            'excused'      => $leave->excused,
                            'special_type' => $leave->special_type,
                            'reason'       => $leave->reason,
                            'start_date'   => $leave->start_date?->toDateString(),
                            'end_date'     => $leave->end_date?->toDateString(),
                            'time_start'   => $leave->time_start,
                            'time_end'     => $leave->time_end,
                            'status'       => $leave->status, // ← 後段の検索用に保持
                            'handle_type'  => $leave->handle_type, // 共有された新カラムも保持
                            'related_event_id' => $eventId,        // 作成したEventのid
                        ],
                    ],
                ]);
            }
        }

        return $events;
    }
}
