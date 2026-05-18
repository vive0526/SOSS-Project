<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

class OrderPlacedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Order $order)
    {
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
        $order = $this->order;
        $order->loadMissing('items');

        $orderNumber = $order->order_number ?: $order->getKey();

        return (new MailMessage)
            ->subject('Order received: ' . $orderNumber)
            ->markdown('mail.order-invoice', [
                'order' => $order,
                'notifiable' => $notifiable,
                'orderNumber' => $orderNumber,
                'logoUrl' => asset('images/sawit-kinabalu-logo.png'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        $order = $this->order;

        return [
            'title' => 'Order received',
            'message' => 'We’ve received your order. Order number: ' . ($order->order_number ?: $order->getKey()) . '.',
            'action_url' => route('customer.orders.show', $order),
            'category' => 'order',
            'level' => 'info',
        ];
    }
}
