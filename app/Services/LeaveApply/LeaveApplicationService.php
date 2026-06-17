<?php

/**
 * 有給・特別休暇の申請登録（バッチ管理・承認通知送信を含む）を担うサービス。
 */
namespace App\Services\LeaveApply;

use App\Enums\LeaveExcused;
use App\Enums\LeaveKind;
use App\Enums\LeaveStatus;
use App\Models\Leave;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class LeaveApplicationService
{
    /**
     * @return array{created:int, skipped:int, skips:array}
     */
    public function apply(
        int $userId,
        array $dates,
        string $reason,
        int $requestedByUserId,
        string $type = LeaveKind::Paid->value,
        ?string $specialType = null,
        ?array $attachmentMeta = null
    ): array {
        $dates = collect($dates)
            ->map(fn ($d) => trim((string) $d))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($dates->isEmpty()) {
            return ['created' => 0, 'skipped' => 0, 'skips' => []];
        }

        $batchId = (string) Str::uuid();

        $result = $type === LeaveKind::Special->value
            ? $this->applySpecial($userId, $dates, $reason, $requestedByUserId, $specialType, $attachmentMeta, $batchId)
            : $this->applyPaid($userId, $dates, $reason, $requestedByUserId, $batchId);

        if (! empty($result['requests'])) {
            $approvalRequest = $result['requests'][0];
            $admins = User::approvalRecipientsForScope(
                $approvalRequest->approvalDistrictId(),
                $approvalRequest->approvalDepartmentId()
            )->get();

            Notification::send(
                $admins,
                new ApprovalRequestedNotification($approvalRequest, count($result['requests']), $batchId)
            );
        }

        return [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'skips' => $result['skips'],
        ];
    }

    private function applyPaid(
        int $userId,
        Collection $dates,
        string $reason,
        int $requestedByUserId,
        string $batchId
    ): array {
        $result = ['created' => 0, 'skipped' => 0, 'skips' => [], 'requests' => []];
        $user = User::find($userId);
        $code = $user?->employee_code ?? '';
        $userLabel = $user ? ($code ? "{$user->name} [{$code}]" : $user->name) : "user#{$userId}";
        $titleBase = "**ALP** {$userLabel} ({$dates->implode(', ')})";

        DB::transaction(function () use ($userId, $user, $dates, $reason, $requestedByUserId, $batchId, $titleBase, &$result) {
            foreach ($dates as $ymd) {
                $already = Leave::query()
                    ->where('user_id', $userId)
                    ->where('kind', LeaveKind::Paid->value)
                    ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
                    ->whereDate('start_date', $ymd)
                    ->exists();

                if ($already) {
                    $result['skipped']++;
                    $result['skips'][$ymd] = '既に申請/承認済みです';

                    continue;
                }

                $leave = Leave::create([
                    'user_id' => $userId,
                    'start_date' => $ymd,
                    'end_date' => null,
                    'kind' => LeaveKind::Paid->value,
                    'excused' => LeaveExcused::Excused->value,
                    'reason' => $reason,
                    'status' => LeaveStatus::Pending->value,
                    'special_type' => null,
                    'district_id' => $user?->district_id,
                    'department_id' => $user?->department_id,
                ]);

                $ar = $leave->approvalRequest()->create([
                    'title' => $titleBase,
                    'requested_by_id' => $requestedByUserId,
                    'current_state' => 'pending',
                    'metadata' => [
                        'batch_id' => $batchId,
                        'leave_id' => $leave->id,
                        'user_id' => $userId,
                        'date' => $ymd,
                        'kind' => LeaveKind::Paid->value,
                        'reason' => $reason,
                        'special_type' => null,
                        'district_id' => $user?->district_id,
                        'department_id' => $user?->department_id,
                    ],
                ]);

                $result['created']++;
                $result['requests'][] = $ar;
            }
        });

        return $result;
    }

    private function applySpecial(
        int $userId,
        Collection $dates,
        string $reason,
        int $requestedByUserId,
        ?string $specialType,
        ?array $attachmentMeta,
        string $batchId
    ): array {
        $result = ['created' => 0, 'skipped' => 0, 'skips' => [], 'requests' => []];
        $user = User::find($userId);
        $code = $user?->employee_code ?? '';
        $userLabel = $user ? ($code ? "{$user->name} [{$code}]" : $user->name) : "user#{$userId}";

        DB::transaction(function () use ($userId, $user, $dates, $reason, $requestedByUserId, $specialType, $attachmentMeta, $batchId, $userLabel, &$result) {
            $start = $dates->min();
            $end = $dates->max();

            if (! $start || ! $end) {
                return;
            }

            $already = Leave::query()
                ->where('user_id', $userId)
                ->where('kind', LeaveKind::Special->value)
                ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('start_date', [$start, $end])
                        ->orWhereBetween('end_date', [$start, $end])
                        ->orWhere(function ($q2) use ($start, $end) {
                            $q2->where('start_date', '<=', $start)->where('end_date', '>=', $end);
                        });
                })
                ->exists();

            if ($already) {
                $result['skipped'] = 1;
                $result['skips']["{$start}〜{$end}"] = '既に申請/承認済みです';

                return;
            }

            $leave = Leave::create([
                'user_id' => $userId,
                'start_date' => $start,
                'end_date' => $end,
                'kind' => LeaveKind::Special->value,
                'excused' => LeaveExcused::Excused->value,
                'reason' => $reason,
                'status' => LeaveStatus::Pending->value,
                'special_type' => $specialType ?: null,
                'district_id' => $user?->district_id,
                'department_id' => $user?->department_id,
            ]);

            if ($attachmentMeta) {
                $leave->attachment()->create($attachmentMeta);
            }

            $periodStr = "{$start}〜{$end}";
            $ar = $leave->approvalRequest()->create([
                'title' => "**Special Leave** {$userLabel} ({$periodStr})",
                'requested_by_id' => $requestedByUserId,
                'current_state' => 'pending',
                'metadata' => [
                    'batch_id' => $batchId,
                    'leave_id' => $leave->id,
                    'user_id' => $userId,
                    'date_from' => $start,
                    'date_to' => $end,
                    'kind' => LeaveKind::Special->value,
                    'reason' => $reason,
                    'special_type' => $specialType ?: null,
                    'district_id' => $user?->district_id,
                    'department_id' => $user?->department_id,
                ],
            ]);

            $result['created'] = 1;
            $result['requests'][] = $ar;
        });

        return $result;
    }
}
