<?php

/**
 * Calls the payment provider to actually issue a refund already reserved in the DB.
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\RefundStatus;
use App\Enums\StockMovementType;
use App\Models\PaymentApiLog;
use App\Models\Refund;
use App\Payments\PaymentManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `ProcessRefund` locks the `Payment` row and reserves the amount as a
 * `Pending` `Refund` synchronously (so the refund cap is enforced against
 * concurrent requests immediately) — the actual external gateway call is
 * dispatched here instead, per this project's "external API calls must be
 * queued" convention. Routed to the `external-api` queue.
 */
class IssueProviderRefund implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(private readonly int $refundId)
    {
        $this->onQueue('external-api');
    }

    public function handle(PaymentManager $payments): void
    {
        $refund = Refund::query()->find($this->refundId);

        if ($refund === null || $refund->status !== RefundStatus::Pending) {
            return;
        }

        $payment = $refund->payment;
        $requestPayload = ['payment_id' => $payment->id, 'amount' => $refund->amount, 'reason' => $refund->reason];

        // See VerifyPaymentWithGateway's matching comment — a transport-
        // level failure here used to skip the PaymentApiLog write
        // entirely, leaving no record of the attempt for reconciliation.
        try {
            $result = $payments->driver($payment->provider)->refund($payment, $refund->amount, $refund->reason);
        } catch (Throwable $e) {
            PaymentApiLog::query()->create([
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'provider' => $payment->provider,
                'action' => 'refund',
                'request_payload' => $requestPayload,
                'response_payload' => ['error' => $e->getMessage()],
                'status_code' => 500,
            ]);

            throw $e;
        }

        PaymentApiLog::query()->create([
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'provider' => $payment->provider,
            'action' => 'refund',
            'request_payload' => $requestPayload,
            'response_payload' => $result->rawResponse,
            'status_code' => $result->success ? 200 : 422,
        ]);

        DB::transaction(function () use ($refund, $payment, $result): void {
            $refund->update([
                'status' => $result->success ? RefundStatus::Success : RefundStatus::Failed,
                'provider_refund_reference' => $result->providerRefundReference,
            ]);

            if (! $result->success) {
                return;
            }

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
        });
    }

    /**
     * Permanent failure after all retries — the Refund row stays Pending
     * (never silently resolved to Success or Failed), so it's visible in
     * the admin panel as stuck and logged here for investigation. The
     * reserved amount still correctly counts against the payment's
     * refundable cap while in this state.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('IssueProviderRefund failed permanently', [
            'refund_id' => $this->refundId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
