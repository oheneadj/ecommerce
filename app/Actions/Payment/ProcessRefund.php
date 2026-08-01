<?php

/**
 * Issues a full or partial refund against a successful payment.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\RefundStatus;
use App\Enums\StockMovementType;
use App\Exceptions\RefundExceedsPaymentException;
use App\Models\Payment;
use App\Models\PaymentApiLog;
use App\Models\Refund;
use App\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The refundable balance (payment.amount minus whatever's already
 * refunded/in-flight) is a finite, contested resource exactly like stock
 * or coupon usage — two concurrent refund requests for the same payment
 * (an admin double-clicking, or a manual refund racing
 * HandleLatePaymentConfirmation's auto-refund) must not both be able to
 * pass the cap check. The Payment row is locked, and a Pending Refund row
 * is inserted (reserving the amount against the cap for any other
 * concurrent check) inside that same locked transaction, before the
 * external gateway call ever happens — the same "reserve first, confirm
 * after" shape as ReserveStockForOrder, and for the same reason a bare
 * check-then-write would defeat the lock entirely.
 *
 * @throws RefundExceedsPaymentException
 */
class ProcessRefund
{
    use AsAction;

    public function __construct(
        private readonly PaymentManager $payments,
    ) {}

    public function handle(Payment $payment, int $amount, ?string $reason = null): Refund
    {
        $refund = DB::transaction(function () use ($payment, $amount, $reason): Refund {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            // Pending refunds count against the cap too — they're a real,
            // in-flight claim on the balance, not yet confirmed but not
            // available to double-spend either.
            $alreadyClaimed = $locked->refunds()
                ->whereIn('status', [RefundStatus::Pending, RefundStatus::Success])
                ->sum('amount');

            if ($amount > ($locked->amount - $alreadyClaimed)) {
                throw new RefundExceedsPaymentException;
            }

            return Refund::query()->create([
                'payment_id' => $locked->id,
                'amount' => $amount,
                'status' => RefundStatus::Pending,
                'reason' => $reason,
            ]);
        }, 3);

        $gateway = $this->payments->driver($payment->provider);
        $result = $gateway->refund($payment, $amount, $reason);

        PaymentApiLog::query()->create([
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'provider' => $payment->provider,
            'action' => 'refund',
            'request_payload' => ['payment_id' => $payment->id, 'amount' => $amount, 'reason' => $reason],
            'response_payload' => $result->rawResponse,
            'status_code' => $result->success ? 200 : 422,
        ]);

        return DB::transaction(function () use ($payment, $refund, $result): Refund {
            $refund->update([
                'status' => $result->success ? RefundStatus::Success : RefundStatus::Failed,
                'provider_refund_reference' => $result->providerRefundReference,
            ]);

            if ($result->success) {
                $order = $payment->order;
                $refundShare = $refund->amount / $payment->amount;

                foreach ($order->items as $item) {
                    $returnedQuantity = (int) round($item->quantity * $refundShare);

                    if ($returnedQuantity > 0) {
                        RecordStockMovement::run(
                            $item->productVariant,
                            StockMovementType::Return,
                            $returnedQuantity,
                            null,
                            "Refund #{$refund->id} for order {$order->order_number}",
                            $refund,
                        );
                    }
                }
            }

            return $refund->fresh();
        });
    }
}
