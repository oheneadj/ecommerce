<?php

/**
 * Resolves a payment that confirms after its stock reservation already expired.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Order\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReservationStatus;
use App\Jobs\GenerateOrderInvoicePdf;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Covers both: a webhook/poll confirming payment after ReleaseExpiredReservations
 * already released the hold, and a reservation flagged `at_risk` by
 * AdjustStockWithReservationCheck whose payment later succeeds anyway.
 *
 * Transaction-only, no lock (AGENTS.md §4a) — this Action runs rarely (a
 * genuinely late confirmation is an edge case, not the checkout hot path),
 * so the check-then-act window it accepts is a documented, deliberate
 * trade-off, not an oversight; `ReserveStockForOrder`/`ApplyCouponToOrder`
 * remain the only two Actions in the system requiring `lockForUpdate()`.
 *
 * If stock is still actually available for every item, the order is
 * fulfilled directly (a fresh `sale` StockMovement per item — the expired
 * reservation is not resurrected). If not, the payment is automatically
 * refunded and the order cancelled, since a paid-but-unfulfillable order
 * is worse than a refunded one.
 */
class HandleLatePaymentConfirmation
{
    use AsAction;

    public function handle(Payment $payment): Order
    {
        $order = $payment->order;
        $order->load('items.productVariant');

        $stockIsAvailable = $order->items->every(function ($item) {
            $variant = $item->productVariant->fresh();

            $reserved = StockReservation::query()
                ->where('product_variant_id', $variant->id)
                ->where('status', StockReservationStatus::Active)
                ->sum('quantity');

            return ($variant->stock - $reserved) >= $item->quantity;
        });

        if ($stockIsAvailable) {
            return $this->fulfill($payment, $order);
        }

        return $this->refundAndCancel($payment, $order);
    }

    private function fulfill(Payment $payment, Order $order): Order
    {
        return DB::transaction(function () use ($payment, $order): Order {
            $payment->update(['status' => PaymentStatus::Success]);

            foreach ($order->items as $item) {
                RecordStockMovement::run(
                    $item->productVariant,
                    StockMovementType::Sale,
                    -$item->quantity,
                    null,
                    'Late payment confirmation — fulfilled directly, stock available',
                    $order,
                );
            }

            $updated = UpdateOrderStatus::run($order, OrderStatus::Paid, note: 'Payment confirmed after reservation expired; stock was still available.');

            DB::afterCommit(fn () => GenerateOrderInvoicePdf::dispatch($order->id));

            return $updated;
        });
    }

    private function refundAndCancel(Payment $payment, Order $order): Order
    {
        return DB::transaction(function () use ($payment, $order): Order {
            $payment->update(['status' => PaymentStatus::Success]);

            ProcessRefund::run($payment, $payment->amount, 'Stock unavailable after delayed payment confirmation');

            return UpdateOrderStatus::run($order, OrderStatus::Cancelled, note: 'Automatically refunded — stock unavailable after delayed payment confirmation.');
        });
    }
}
