<?php

/**
 * Verifies a pending payment directly with its provider, off the request/console cycle.
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Payment\MarkPaymentFailed;
use App\Actions\Payment\SettlePaymentSuccess;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentApiLog;
use App\Payments\PaymentManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shared by HandlePaymentWebhook (dispatched per inbound webhook) and
 * VerifyPendingPayments (dispatched per still-pending payment on the
 * polling sweep) — an external gateway call has no place blocking either
 * the webhook's HTTP response or the scheduler's console process, per this
 * project's "external API calls must be queued" convention. Routed to the
 * `external-api` queue so a slow/flaky provider never delays time-sensitive
 * notification jobs on the `notifications` queue.
 *
 * Re-checks the payment is still Pending both before and after the gateway
 * call — whichever of the webhook path or the polling path gets here
 * first wins, exactly as when this logic ran inline.
 */
class VerifyPaymentWithGateway implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(private readonly int $paymentId)
    {
        $this->onQueue('external-api');
    }

    public function handle(PaymentManager $payments): void
    {
        $payment = Payment::query()->find($this->paymentId);

        if ($payment === null || $payment->status !== PaymentStatus::Pending || $payment->provider_reference === null) {
            return;
        }

        $gateway = $payments->driver($payment->provider);
        $result = $gateway->verify($payment->provider_reference);

        PaymentApiLog::query()->create([
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'provider' => $payment->provider,
            'action' => 'verify',
            'request_payload' => ['provider_reference' => $payment->provider_reference],
            'response_payload' => $result->rawResponse,
            'status_code' => 200,
        ]);

        $payment->refresh();

        if ($payment->status !== PaymentStatus::Pending) {
            return;
        }

        if ($result->status === PaymentStatus::Success) {
            SettlePaymentSuccess::run($payment);
        } elseif ($result->status === PaymentStatus::Failed) {
            MarkPaymentFailed::run($payment);
        }
    }

    /**
     * Permanent failure after all retries — the payment stays Pending, so
     * the next VerifyPendingPayments sweep will pick it up again; logged
     * here so a persistently-failing provider integration doesn't go
     * unnoticed.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('VerifyPaymentWithGateway failed permanently', [
            'payment_id' => $this->paymentId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
