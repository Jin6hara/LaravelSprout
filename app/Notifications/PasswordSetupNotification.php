<?php

/**
 * 招待・パスワードリセット用のセットアップリンクをメール送信する通知クラス。
 */
namespace App\Notifications;

use App\Models\PasswordSetupToken;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class PasswordSetupNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $plainToken,
        private string $purpose,
        private Carbon $expiresAt,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isInvite = $this->purpose === PasswordSetupToken::PURPOSE_INVITE;
        $subject = $isInvite ? 'Account Invitation' : 'Password Reset';
        $action = $isInvite ? 'Create Password' : 'Reset Password';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($isInvite
                ? 'Your account has been created. Please use the link below to create your password.'
                : 'We received a request to reset your password.')
            ->action($action, route('password.setup.show', ['token' => $this->plainToken]))
            ->line('This link expires at ' . $this->expiresAt->format('Y-m-d H:i') . '.')
            ->line('If you did not request this email, you can safely ignore it.');
    }
}
