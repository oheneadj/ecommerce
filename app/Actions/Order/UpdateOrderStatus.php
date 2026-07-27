<?php

/**
 * Transitions an order to a new status, recording the change in its history.
 */

declare(strict_types=1);

namespace App\Actions\Order;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A plain transaction — no locking. Every status change must go through
 * this Action rather than a raw `$order->update(['status' => ...])`, so
 * `order_status_histories` never misses an entry.
 */
class UpdateOrderStatus
{
    use AsAction;

    public function handle(Order $order, OrderStatus $status, ?User $changedBy = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $status, $changedBy, $note): Order {
            $order->update(['status' => $status]);

            $order->statusHistories()->create([
                'status' => $status,
                'note' => $note,
                'changed_by' => $changedBy?->id,
            ]);

            $this->syncShipmentDelivery($order, $status);

            return $order;
        });
    }

    /**
     * Marking an order Delivered also marks its shipment Delivered, since
     * there's no separate admin action for that — the order's own status is
     * the one thing staff actually update, and the shipment should follow it.
     */
    private function syncShipmentDelivery(Order $order, OrderStatus $status): void
    {
        if ($status !== OrderStatus::Delivered) {
            return;
        }

        $shipment = $order->shipment;

        if ($shipment === null || $shipment->status === ShipmentStatus::Delivered) {
            return;
        }

        $shipment->update([
            'status' => ShipmentStatus::Delivered,
            'delivered_at' => now(),
        ]);
    }
}
