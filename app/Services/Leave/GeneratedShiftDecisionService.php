<?php

namespace App\Services\Leave;

use App\Enums\LeaveStatus;
use App\Models\ApprovalRequest;
use App\Models\Leave;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class GeneratedShiftDecisionService
{
    public const ACTION_DETACH = 'detach';
    public const ACTION_DELETE = 'delete';

    public function normalizeAction(?string $action): ?string
    {
        return in_array($action, [self::ACTION_DETACH, self::ACTION_DELETE], true)
            ? $action
            : null;
    }

    public function hasGeneratedShifts(Leave $leave): bool
    {
        return $leave->generatedEvents()->exists();
    }

    public function requiresActionForStatus(Leave $leave, string $status, ?string $action): bool
    {
        return !in_array($status, LeaveStatus::snapshotValues(), true)
            && $this->hasGeneratedShifts($leave)
            && $this->normalizeAction($action) === null;
    }

    public function applyAction(Leave $leave, string $action): int
    {
        $action = $this->normalizeAction($action);

        if ($action === self::ACTION_DELETE) {
            return $leave->generatedEvents()->delete();
        }

        if ($action === self::ACTION_DETACH) {
            return $leave->generatedEvents()->update(['source_leave_id' => null]);
        }

        throw new InvalidArgumentException('Invalid generated shift action.');
    }

    public function detailsForLeave(Leave $leave): Collection
    {
        $leave->loadMissing([
            'generatedEvents.originalUser:id,first_name,family_name,employee_code',
            'generatedEvents.assignedUser:id,first_name,family_name,employee_code',
        ]);

        return $leave->generatedEvents
            ->sortBy([
                ['event_date', 'asc'],
                ['start_time', 'asc'],
            ])
            ->map(fn($event) => $this->eventDetails($leave, $event))
            ->values();
    }

    public function detailsForApprovalRequest(ApprovalRequest $approvalRequest): Collection
    {
        return $this->leavesForApprovalRequest($approvalRequest)
            ->flatMap(fn(Leave $leave) => $this->detailsForLeave($leave))
            ->values();
    }

    public function leavesForApprovalRequest(ApprovalRequest $approvalRequest): Collection
    {
        $approvalRequest->loadMissing('approvable');
        $approvable = $approvalRequest->approvable;

        if (!$approvable instanceof Leave) {
            return collect();
        }

        $batchId = $approvalRequest->metadata['batch_id'] ?? null;

        if (!$batchId) {
            return collect([$approvable]);
        }

        return ApprovalRequest::query()
            ->where('approvable_type', Leave::class)
            ->where('metadata->batch_id', $batchId)
            ->with('approvable')
            ->get()
            ->pluck('approvable')
            ->filter(fn($leave) => $leave instanceof Leave)
            ->unique('id')
            ->values();
    }

    private function eventDetails(Leave $leave, $event): array
    {
        $original = trim(($event->originalUser?->first_name ?? '') . ' ' . ($event->originalUser?->family_name ?? ''));
        $assigned = trim(($event->assignedUser?->first_name ?? '') . ' ' . ($event->assignedUser?->family_name ?? ''));

        return [
            'leave_id' => $leave->id,
            'id' => $event->id,
            'date' => optional($event->event_date)->format('Y-m-d') ?: '-',
            'original' => $original !== '' ? $original : '-',
            'assigned' => $assigned !== '' ? $assigned : '-',
            'school' => $event->school_name ?: '-',
            'time' => trim(optional($event->start_time)->format('H:i') . ' - ' . optional($event->end_time)->format('H:i'), ' -') ?: '-',
            'lesson' => $event->Lesson ?: '-',
            'status' => $event->status ?: '-',
            'notes' => $event->notes ?: '-',
        ];
    }
}
