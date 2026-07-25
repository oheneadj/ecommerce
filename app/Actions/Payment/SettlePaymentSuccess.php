<?php

/**
 * Applies the effects of a payment succeeding, however that success was learned.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Order\GenerateOrderInvoice;
use App\Actions\Order\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReservationStatus;
use App\Models\Payment;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Shared by HandlePaymentWebhook and VerifyPendingPayments so a payment
 * confirmed by either path is settled identically. If every order item's
 * stock reservation is still active, fulfillment proceeds directly
 * (transaction-only, no lock — AGENTS.md §4a); if any reservation has
 * already expired/released, control passes to HandleLatePaymentConfirmation.
 * The PDF receipt is generated only after the transaction commits (BRD
 * E6.4) — file I/O has no place inside a DB transaction.
 */
class SettlePaymentSuccess
{
    use AsAction;

    public function handle(Payment $payment): void
    {
        $order = $payment->order;
        $order->load('items.productVariant');

        $reservationsStillActive = $order->items->every(
            fn ($item) => StockReservation::query()
                ->where('product_variant_id', $item->product_variant_id)
                ->where('order_id', $order->id)
                ->where('status', StockReservationStatus::Active)
                ->exists(),
        );

        if (! $reservationsStillActive) {
            HandleLatePaymentConfirmation::run($payment);

            return;
        }

        DB::transaction(function () use ($payment, $order): void {
            $payment->update(['status' => PaymentStatus::Success]);

            foreach ($order->items as $item) {
                StockReservation::query()
                    ->where('product_variant_id', $item->product_variant_id)
                    ->where('order_id', $order->id)
                    ->where('status', StockReservationStatus::Active)
                    ->update(['status' => StockReservationStatus::Fulfilled]);

                RecordStockMovement::run(
                    $item->productVariant,
                    StockMovementType::Sale,
                    -$item->quantity,
                    null,
                    'Payment confirmed',
                    $order,
                );
            }

            UpdateOrderStatus::run($order, OrderStatus::Paid, note: 'Payment confirmed.');

            DB::afterCommit(fn () => GenerateOrderInvoice::run($order));
        });
    }
}
