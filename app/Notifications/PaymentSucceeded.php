<?php

/**
 * Notifies a customer that their payment succeeded.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Notifications\Support\BrandedMessage;
use Illuminate\Notifications\Messages\MailMessage;

class PaymentSucceeded extends OrderNotification
{
    /**
     * Confirms the payment succeeded, signed with the store's business name.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        return BrandedMessage::mail(
            (new MailMessage)
                ->subject("Payment confirmed for order {$this->order->order_number}")
                ->greeting('Payment received!')
                ->line("Your payment of {$this->order->grand_total_formatted} for order {$this->order->order_number} was successful.")
                ->line('Your receipt is attached to your order in your account.'),
        );
    }

    /**
     * The SMS equivalent, prefixed with the store's business name.
     */
    public function toSms(mixed $notifiable): string
    {
        return BrandedMessage::sms("Payment confirmed for order {$this->order->order_number} — {$this->order->grand_total_formatted}. Thank you!");
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'message' => "Payment confirmed for order {$this->order->order_number}.",
        ];
    }
}
