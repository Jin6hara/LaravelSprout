<?php

/**
 * 現在の管理スコープに絞り込んだ通知の取得・既読管理を担うサービス。
 */
namespace App\Services\Notifications;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Services\CurrentScopeService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class ScopedNotificationService
{
    public function __construct(private CurrentScopeService $scopeService) {}

    public function visibleNotifications(User $user, int $limit = 100): Collection
    {
        return $user->notifications()
            ->latest()
            ->get()
            ->filter(fn (DatabaseNotification $notification) => $this->isVisible($user, $notification))
            ->take($limit)
            ->values();
    }

    public function unreadCount(User $user): int
    {
        if (! $user->hasRole(['admin', 'super_admin'])) {
            return 0;
        }

        return $user->unreadNotifications()
            ->get()
            ->filter(fn (DatabaseNotification $notification) => $this->isVisible($user, $notification))
            ->count();
    }

    public function markVisibleUnreadAsRead(User $user): void
    {
        $user->unreadNotifications()
            ->get()
            ->filter(fn (DatabaseNotification $notification) => $this->isVisible($user, $notification))
            ->each->markAsRead();
    }

    public function isVisible(User $user, DatabaseNotification $notification): bool
    {
        if ($notification->notifiable_type !== User::class || (int) $notification->notifiable_id !== $user->id) {
            return false;
        }

        $approvalRequest = $this->approvalRequestFor($notification);
        if (! $approvalRequest) {
            return true;
        }

        if (! $user->hasRole(['admin', 'super_admin'])) {
            return true;
        }

        return $this->matchesCurrentScope($approvalRequest);
    }

    public function matchesCurrentScope(ApprovalRequest $approvalRequest): bool
    {
        $districtId = $approvalRequest->approvalDistrictId();
        $departmentId = $approvalRequest->approvalDepartmentId();

        return $districtId !== null
            && $departmentId !== null
            && $districtId === $this->scopeService->currentDistrictId()
            && $departmentId === $this->scopeService->currentDepartmentId();
    }

    private function approvalRequestFor(DatabaseNotification $notification): ?ApprovalRequest
    {
        $approvalRequestId = $notification->data['approval_request_id'] ?? null;

        return $approvalRequestId ? ApprovalRequest::find($approvalRequestId) : null;
    }
}
