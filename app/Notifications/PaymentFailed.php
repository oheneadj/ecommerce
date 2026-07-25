<?php

/**
 * Notifies a customer that their payment attempt failed.
 */

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class PaymentFailed extends OrderNotification
{
    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Payment failed for order {$this->order->order_number}")
            ->greeting('Payment unsuccessful')
            ->line("We couldn't process your payment of {$this->order->grand_total_formatted} for order {$this->order->order_number}.")
            ->line('Please try again, or use a different payment method.');
    }

    public function toSms(mixed $notifiable): string
    {
        return "Payment for order {$this->order->order_number} failed. Please try again.";
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'message' => "Payment failed for order {$this->order->order_number}.",
        ];
    }
}
