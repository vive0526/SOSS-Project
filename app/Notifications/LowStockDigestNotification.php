<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowStockDigestNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $lowStockCount,
        private readonly int $outOfStockCount,
        private readonly string $digestDate
    ) {
    }

    /**
     * @param mixed $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * @param mixed $notifiable
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable): array
    {
        $parts = [];
        if ($this->lowStockCount > 0) {
            $parts[] = "Low stock: {$this->lowStockCount}";
        }
        if ($this->outOfStockCount > 0) {
            $parts[] = "Out of stock: {$this->outOfStockCount}";
        }

        return [
            'title' => 'Inventory low stock alert',
            'message' => implode(' • ', $parts),
            'action_url' => route('products.inventory'),
            'category' => 'inventory',
            'level' => ($this->outOfStockCount > 0) ? 'warning' : 'info',
            'digest_date' => $this->digestDate,
        ];
    }
}

