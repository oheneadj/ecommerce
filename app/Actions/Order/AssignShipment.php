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

    /**
     * Only the *first* dispatch stamps dispatched_at and notifies the
     * customer — re-running this to fix a shipping method or tracking
     * number typo shouldn't reset the dispatch time or re-send "your order
     * has shipped" every time.
     */
    public function handle(Order $order, ShippingMethod $shippingMethod, ?string $trackingNumber = null): Shipment
    {
        $existing = Shipment::query()->where('order_id', $order->id)->first();
        $isFirstDispatch = $existing === null || $existing->status === ShipmentStatus::Pending;

        $attributes = [
            'shipping_method_id' => $shippingMethod->id,
            'tracking_number' => $trackingNumber,
        ];

        if ($isFirstDispatch) {
            $attributes['status'] = ShipmentStatus::Dispatched;
            $attributes['dispatched_at'] = now();
        }

        $shipment = Shipment::query()->updateOrCreate(['order_id' => $order->id], $attributes);

        if ($isFirstDispatch) {
            SafeNotifier::send(OrderRecipient::for($order), new OrderShipped($order, $shipment));
        }

        return $shipment;
    }
}
