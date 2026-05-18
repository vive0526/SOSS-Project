<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

class AccountStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $newStatus,
        private readonly ?string $previousStatus = null,
        private readonly ?string $reason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        if (Schema::hasTable('notifications')) {
            $channels[] = 'database';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = match ($this->newStatus) {
            'inactive' => 'Your account has been temporarily deactivated. Please contact support if you think this is a mistake.',
            'active' => 'Your account is active again. You can log in and continue shopping.',
            default => 'Your account status has been updated.',
        };

        if ($this->reason) {
            $message .= ' Reason: ' . $this->reason;
        }

        return (new MailMessage)
            ->subject('Account status updated')
            ->greeting('Hi ' . ($notifiable->name ?? ''))
            ->line($message)
            ->line('If you need help, please contact support.');
    }

    public function toArray(object $notifiable): array
    {
        $message = match ($this->newStatus) {
            'inactive' => 'Your account has been temporarily deactivated. Please contact support if you think this is a mistake.',
            'active' => 'Your account is active again. You can log in and continue shopping.',
            default => 'Your account status has been updated.',
        };
        if ($this->reason) {
            $message .= " Reason: {$this->reason}";
        }

        return [
            'title' => 'Account status updated',
            'message' => $message,
            'category' => 'system',
            'level' => $this->newStatus === 'inactive' ? 'warning' : 'info',
            'meta' => [
                'previous_status' => $this->previousStatus,
                'new_status' => $this->newStatus,
            ],
        ];
    }
}
