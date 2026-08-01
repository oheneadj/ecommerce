<?php

/**
 * Polls still-pending payments directly with their provider, as a fallback
 * for a slow or missing webhook delivery.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Jobs\VerifyPaymentWithGateway;
use App\Models\Payment;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Scheduled roughly every 2 minutes. Only considers payments pending
 * beyond a grace period, so a payment that's merely a few seconds old
 * (webhook likely still in flight) isn't polled unnecessarily.
 *
 * Each verification is dispatched to VerifyPaymentWithGateway rather than
 * made inline here — the same job the webhook path uses, so a slow/flaky
 * provider on one payment never delays checking the rest of the sweep,
 * and whichever of polling or webhook settles a payment first still wins
 * (enforced inside the job itself via the same `payment->status !==
 * Pending` guard).
 */
class VerifyPendingPayments
{
    use AsAction;

    private const GRACE_PERIOD_MINUTES = 2;

    public function handle(): int
    {
        $pending = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('provider_reference')
            ->where('created_at', '<', now()->subMinutes(self::GRACE_PERIOD_MINUTES))
            ->get();

        foreach ($pending as $payment) {
            VerifyPaymentWithGateway::dispatch($payment->id);
        }

        return $pending->count();
    }
}
