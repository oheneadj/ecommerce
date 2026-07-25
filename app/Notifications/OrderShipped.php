<?php

/**
 * Notifies a customer that their order has shipped.
 */

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Notifications\Messages\MailMessage;

class OrderShipped extends OrderNotification
{
    public function __construct(Order $order, private readonly Shipment $shipment)
    {
        parent::__construct($order);
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Order {$this->order->order_number} has shipped")
            ->greeting('Your order is on its way!')
            ->line("Order {$this->order->order_number} has shipped via {$this->shipment->shippingMethod->name}.");

        if ($this->shipment->tracking_number !== null) {
            $message->line("Tracking number: {$this->shipment->tracking_number}");
        }

        return $message;
    }

    public function toSms(mixed $notifiable): string
    {
        $tracking = $this->shipment->tracking_number !== null ? " Tracking: {$this->shipment->tracking_number}" : '';

        return "Order {$this->order->order_number} has shipped.{$tracking}";
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
