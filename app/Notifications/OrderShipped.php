<?php

/**
 * Notifies a customer that their order has shipped.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Models\Shipment;
use App\Notifications\Support\BrandedMessage;
use Illuminate\Notifications\Messages\MailMessage;

class OrderShipped extends OrderNotification
{
    /**
     * @param  Shipment  $shipment  the shipment record that just dispatched
     */
    public function __construct(Order $order, private readonly Shipment $shipment)
    {
        parent::__construct($order);
    }

    /**
     * Announces the shipment, signed with the store's business name.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Order {$this->order->order_number} has shipped")
            ->greeting('Your order is on its way!')
            ->line("Order {$this->order->order_number} has shipped via {$this->shipment->shippingMethod->name}.");

        if ($this->shipment->tracking_number !== null) {
            $message->line("Tracking number: {$this->shipment->tracking_number}");
        }

        return BrandedMessage::mail($message);
    }

    /**
     * The SMS equivalent, prefixed with the store's business name.
     */
    public function toSms(mixed $notifiable): string
    {
        $tracking = $this->shipment->tracking_number !== null ? " Tracking: {$this->shipment->tracking_number}" : '';

        return BrandedMessage::sms("Order {$this->order->order_number} has shipped.{$tracking}");
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'tracking_number' => $this->shipment->tracking_number,
            'message' => "Order {$this->order->order_number} has shipped.",
        ];
    }
}
