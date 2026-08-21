<?php

/**
 * Transitions an order to a new status, recording the change in its history.
 */

declare(strict_types=1);

namespace App\Actions\Order;

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidOrderStatusTransitionException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Every status change must go through this Action rather than a raw
 * `$order->update(['status' => ...])`, so `order_status_histories` never
 * misses an entry.
 *
 * Requesting the order's current status is a no-op (returns unchanged,
 * writes nothing) rather than an error, since the admin "Update status"
 * modal defaults its Select to the current value — submitting without
 * changing it must not throw. Any other target must be a legal transition
 * per OrderStatus::allowedNextStatuses(), or this throws.
 *
 * @throws InvalidOrderStatusTransitionException when the target status isn't reachable from the order's current one
 */
class UpdateOrderStatus
{
    use AsAction;

    public function handle(Order $order, OrderStatus $status, ?User $changedBy = null, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $status, $changedBy, $note): Order {
            // Locked and re-read here, not evaluated against the
            // in-memory $order passed in — two concurrent requests to
            // change the same order (e.g. a doubled-up admin click)
            // would otherwise both see the same stale pre-transaction
            // status, both pass the transition check, and both restock
            // a cancelled order's items (the transition-to-terminal
            // guard only protects against running twice on the SAME
            // request's fresh state, not against a second request that
            // read its status before the first one committed).
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($status === $lockedOrder->status) {
                return $lockedOrder;
            }

            if (! in_array($status, $lockedOrder->status->allowedNextStatuses(), true)) {
                throw new InvalidOrderStatusTransitionException($lockedOrder->status, $status);
            }

            $this->restockIfCancellingAFulfilledOrder($lockedOrder, $status);

            $lockedOrder->update(['status' => $status]);

            $lockedOrder->statusHistories()->create([
                'status' => $status,
                'note' => $note,
                'changed_by' => $changedBy?->id,
            ]);

            $this->syncShipmentDelivery($lockedOrder, $status);

            return $lockedOrder;
        });
    }

    /**
     * Stock is decremented once, the moment a payment settles (see
     * SettlePaymentSuccess/HandleLatePaymentConfirmation) — it was never
     * automatically returned when a staff member later cancels that
     * already-paid order by hand. Cancelling from any status that implies
     * stock was already sold restores it via the same ledger every other
     * stock change goes through, exactly once (the transition guard above
     * makes a status terminal the moment it's Cancelled, so this can never
     * run twice for the same order).
     */
    private function restockIfCancellingAFulfilledOrder(Order $order, OrderStatus $status): void
    {
        if ($status !== OrderStatus::Cancelled || ! $order->status->hasDecrementedStock()) {
            return;
        }

        $order->load('items.productVariant');

        foreach ($order->items as $item) {
            RecordStockMovement::run(
                $item->productVariant,
                StockMovementType::Return,
                $item->quantity,
                null,
                'Order cancelled after payment — stock returned',
                $order,
            );
        }
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
