<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use App\Models\ApprovalRequest;


class ApprovalRequestedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public ApprovalRequest $approvalRequest)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Approval Requested')
            ->line('An approval request has been submitted.')
            ->action('View Request', url('/'))
            ->line('Thank you!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->approvalRequest->title,
            'approval_request_id' => $this->approvalRequest->id,
            'approvable_type' => $this->approvalRequest->approvable_type,
            'approvable_id' => $this->approvalRequest->approvable_id,
            'requested_by' => $this->approvalRequest->requester->name,
        ];
    }
}
