<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CompleteProfileNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $missingFields = [])
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $missing = array_values(array_filter($this->missingFields));

        return [
            'title' => 'Complete your profile to checkout',
            'message' => empty($missing)
                ? 'Update your phone number and shipping address details to proceed with checkout.'
                : 'Missing: ' . implode(', ', $missing) . '. Update your profile to proceed with checkout.',
            'action_url' => route('profile.edit'),
            'category' => 'system',
            'level' => 'warning',
            'requires_action' => true,
            'action_label' => 'Update profile',
        ];
    }
}
