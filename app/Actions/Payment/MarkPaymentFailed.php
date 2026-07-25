<?php

/**
 * Marks a payment as failed and notifies the customer.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Notifications\PaymentFailed;
use App\Notifications\Support\OrderRecipient;
use App\Notifications\Support\SafeNotifier;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Shared by HandlePaymentWebhook and VerifyPendingPayments, mirroring
 * SettlePaymentSuccess's role for the failure branch — a single write, so
 * no transaction wrapping is needed; the notification fires immediately
 * after (there's no outer transaction to wait for commit on here).
 */
class MarkPaymentFailed
{
    use AsAction;

    public function handle(Payment $payment): void
    {
        $payment->update(['status' => PaymentStatus::Failed]);

        SafeNotifier::send(OrderRecipient::for($payment->order), new PaymentFailed($payment->order));
    }
}
