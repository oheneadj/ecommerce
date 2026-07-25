<?php

/**
 * Polls still-pending payments directly with their provider, as a fallback
 * for a slow or missing webhook delivery.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentApiLog;
use App\Payments\PaymentManager;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Scheduled roughly every 2 minutes. Only considers payments pending
 * beyond a grace period, so a payment that's merely a few seconds old
 * (webhook likely still in flight) isn't polled unnecessarily.
 *
 * Funnels through the exact same status-update logic as
 * HandlePaymentWebhook's success/late-confirmation handling, so whichever
 * of polling or webhook arrives first "wins" and neither double-processes
 * — enforced by the same `payment->status !== Pending` guard.
 */
class VerifyPendingPayments
{
    use AsAction;

    private const GRACE_PERIOD_MINUTES = 2;

    public function __construct(
        private readonly PaymentManager $payments,
    ) {}

    public function handle(): int
    {
        $pending = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('created_at', '<', now()->subMinutes(self::GRACE_PERIOD_MINUTES))
            ->get();

        foreach ($pending as $payment) {
            $this->verifyOne($payment);
        }

        return $pending->count();
    }

    private function verifyOne(Payment $payment): void
    {
        if ($payment->provider_reference === null) {
            return;
        }

        $gateway = $this->payments->driver($payment->provider);
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

        // Re-check status hasn't already been settled by a concurrently
        // arriving webhook — whichever gets here first wins.
        $payment->refresh();

        if ($payment->status !== PaymentStatus::Pending) {
            return;
        }

        if ($result->status === PaymentStatus::Success) {
            SettlePaymentSuccess::run($payment);
        } elseif ($result->status === PaymentStatus::Failed) {
            $payment->update(['status' => PaymentStatus::Failed]);
        }
    }
}
