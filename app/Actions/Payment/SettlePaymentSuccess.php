<?php

/**
 * Applies the effects of a payment succeeding, however that success was learned.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Inventory\RecordStockMovement;
use App\Actions\Order\UpdateOrderStatus;
use App\Actions\Wishlist\RemoveOrderItemsFromWishlist;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Enums\StockReservationStatus;
use App\Jobs\GenerateOrderInvoicePdf;
use App\Models\Payment;
use App\Models\StockReservation;
use App\Notifications\PaymentSucceeded;
use App\Notifications\Support\OrderRecipient;
use App\Notifications\Support\SafeNotifier;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Shared by HandlePaymentWebhook and VerifyPendingPayments so a payment
 * confirmed by either path is settled identically. The payment row is
 * locked for the whole call (see the `lockForUpdate()` below) — two
 * concurrent settlement attempts for the same payment (webhook delivery
 * racing the polling sweep, or a duplicated job dispatch) used to both pass
 * a "still Pending?" check taken outside any lock, both proceed to fulfill,
 * and both unconditionally decrement stock even though only one reservation
 * update actually matched a row — a real double-sell. Whoever gets the lock
 * first settles the payment; the other sees a non-Pending status once it
 * acquires the lock and does nothing.
 * If every order item's stock reservation is still active, fulfillment
 * proceeds directly; if any reservation has already expired/released,
 * control passes to HandleLatePaymentConfirmation (still inside this same
 * lock, so it's covered too).
 * The PDF receipt is dispatched to a queued job only after the transaction
 * commits (BRD E6.4) — file I/O has no place inside a DB transaction, nor
 * blocking the webhook/console process that confirmed the payment.
 */
class SettlePaymentSuccess
{
    use AsAction;

    public function handle(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->status !== PaymentStatus::Pending) {
                return;
            }

            $order = $locked->order;
            $order->load('items.productVariant');

            $reservationsStillActive = $order->items->every(
                fn ($item) => StockReservation::query()
                    ->where('product_variant_id', $item->product_variant_id)
                    ->where('order_id', $order->id)
                    ->where('status', StockReservationStatus::Active)
                    ->exists(),
            );

            if (! $reservationsStillActive) {
                HandleLatePaymentConfirmation::run($locked);

                return;
            }

            $locked->update(['status' => PaymentStatus::Success]);

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

            RemoveOrderItemsFromWishlist::run($order);

            DB::afterCommit(function () use ($order): void {
                GenerateOrderInvoicePdf::dispatch($order->id);
                SafeNotifier::send(OrderRecipient::for($order), new PaymentSucceeded($order));
            });
        });
    }
}
