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
 * Transaction-only, no lock (AGENTS.md §4a). `amount` is capped against
 * `payment.amount` minus whatever has already been refunded — enforced
 * here since it's cross-row arithmetic the database can't constrain.
 * Restores stock via a `return` StockMovement for every affected order
 * item, proportional to the refunded amount's share of the order.
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
        $alreadyRefunded = $payment->refunds()->where('status', RefundStatus::Success)->sum('amount');

        if ($amount > ($payment->amount - $alreadyRefunded)) {
            throw new RefundExceedsPaymentException;
        }

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

        return DB::transaction(function () use ($payment, $amount, $reason, $result): Refund {
            $refund = Refund::query()->create([
                'payment_id' => $payment->id,
                'amount' => $amount,
                'status' => $result->success ? RefundStatus::Success : RefundStatus::Failed,
                'provider_refund_reference' => $result->providerRefundReference,
                'reason' => $reason,
            ]);

            if ($result->success) {
                $order = $payment->order;
                $refundShare = $amount / $payment->amount;

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

            return $refund;
        });
    }
}
