<?php

/**
 * Notifies a customer that their order was placed.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Support\BrandedMessage;
use Illuminate\Notifications\Messages\MailMessage;

class OrderPlaced extends OrderNotification
{
    /**
     * Confirms the order was received, signed with the store's business name.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return BrandedMessage::mail(
            (new MailMessage)
                ->subject("Order {$this->order->order_number} received")
                ->greeting('Thanks for your order!')
                ->line("We've received order {$this->order->order_number} for {$this->order->grand_total_formatted}.")
                ->line('We\'ll let you know as soon as payment is confirmed.'),
        );
    }

    /**
     * The SMS equivalent, prefixed with the store's business name.
     */
    public function toSms(mixed $notifiable): string
    {
        return BrandedMessage::sms("Order {$this->order->order_number} received — total {$this->order->grand_total_formatted}. We'll confirm once payment clears.");
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'message' => "Order {$this->order->order_number} placed.",
        ];
    }
}
