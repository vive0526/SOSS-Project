<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;

class OrderPaymentVerifiedNotification extends Notification
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

        return (new MailMessage)
            ->subject('Payment verified: ' . ($order->order_number ?: $order->getKey()))
            ->greeting('Hi ' . ($notifiable->name ?? ''))
            ->line('Your payment has been verified and your order is confirmed.')
            ->line('Order number: ' . ($order->order_number ?: $order->getKey()))
            ->line('Total paid: RM ' . number_format((float) ($order->total_amount ?? 0), 2))
            ->action('View your order', route('customer.orders.show', $order))
            ->line('Thank you for shopping with us.');
    }

    public function toArray(object $notifiable): array
    {
        $order = $this->order;

        return [
            'title' => 'Payment verified',
            'message' => 'Your payment has been verified for order ' . ($order->order_number ?: $order->getKey()) . '.',
            'action_url' => route('customer.orders.show', $order),
            'category' => 'order',
            'level' => 'success',
        ];
    }
}

