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
    public function __construct(
        public ApprovalRequest $approvalRequest,
        public int $totalCount = 1,
        public ?string $batchId = null,
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // ★ 件数によって件名に「(n件)」を付ける
        $subject = '承認依頼: ' . $this->approvalRequest->title;
        if ($this->totalCount > 1) {
            $subject .= "（{$this->totalCount}件）";
        }

        return (new MailMessage)
            ->subject($subject)
            ->line('新しい承認依頼があります。')
            ->action('承認画面を開く', route('approvals.show', $this->approvalRequest))
            ->line('ご対応をお願いします。');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title'               => $this->approvalRequest->title,
            'approval_request_id' => $this->approvalRequest->id,
            'url'                 => route('approvals.show', $this->approvalRequest), // ベル通知から遷移
            // ★ まとめ申請用のメタ情報
            'total_count'         => $this->totalCount,
            'batch_id'            => $this->batchId,
        ];
    }
}
