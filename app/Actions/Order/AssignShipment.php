<?php

/**
 * Creates or updates the shipment for an order.
 */

declare(strict_types=1);

namespace App\Actions\Order;

use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Notifications\OrderShipped;
use App\Notifications\Support\OrderRecipient;
use App\Notifications\Support\SafeNotifier;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A single-location deployment ships an order as one parcel — no splitting
 * (matches FR-2.4's single physical location assumption). Not a contested
 * or finite resource, so no locking or transaction wrapping is needed
 * beyond the single upsert this Action performs.
 */
class AssignShipment
{
    use AsAction;

    public function handle(Order $order, ShippingMethod $shippingMethod, ?string $trackingNumber = null): Shipment
    {
        $shipment = Shipment::query()->updateOrCreate(
            ['order_id' => $order->id],
            [
                'shipping_method_id' => $shippingMethod->id,
                'tracking_number' => $trackingNumber,
                'status' => ShipmentStatus::Dispatched,
                'dispatched_at' => now(),
            ],
        );

        SafeNotifier::send(OrderRecipient::for($order), new OrderShipped($order, $shipment));

        return $shipment;
    }
}
