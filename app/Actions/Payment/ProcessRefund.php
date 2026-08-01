<?php

/**
 * Issues a full or partial refund against a successful payment.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\RefundStatus;
use App\Exceptions\RefundExceedsPaymentException;
use App\Jobs\IssueProviderRefund;
use App\Models\Payment;
use App\Models\Refund;
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
 * concurrent check) inside that same locked transaction — the same
 * "reserve first, confirm after" shape as ReserveStockForOrder.
 *
 * The actual external gateway call is dispatched to IssueProviderRefund
 * rather than made here — an external API call has no place blocking the
 * admin request that triggered it (this project's own "external API calls
 * must be queued" convention). This Action returns as soon as the amount
 * is reserved; the Refund stays Pending until the queued job resolves it
 * to Success/Failed.
 *
 * @throws RefundExceedsPaymentException
 */
class ProcessRefund
{
    use AsAction;

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

        IssueProviderRefund::dispatch($refund->id);

        return $refund;
    }
}
