<?php

/**
 * Processes an inbound webhook notification from a payment provider.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentApiLog;
use App\Models\WebhookEvent;
use App\Payments\PaymentManager;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Signature verification happens before anything else — an unverified
 * request is still logged (so there's a forensic trail) but never acted
 * upon. The webhook event is written and committed on its own first
 * (technical-design §4g's audit-log exemption: a rollback of business
 * processing must never erase evidence that the notification arrived),
 * then business processing runs in its own transaction.
 *
 * Idempotency is layered: `webhook_events` has a unique `(provider,
 * event_id)` index (DB-level), and `processed_at` is checked before any
 * side effect runs (app-level) — both must hold even if one is somehow
 * bypassed, so a retried delivery never double-fulfills an order.
 *
 * The webhook's own reported status is never trusted directly — the
 * payment is always re-verified server-side via `PaymentGateway::verify()`
 * (critical for Paystack, where a client-side redirect must never be
 * trusted alone).
 */
class HandlePaymentWebhook
{
    use AsAction;

    public function __construct(
        private readonly PaymentManager $payments,
    ) {}

    public function handle(Request $request, string $provider): void
    {
        $gateway = $this->payments->driver($provider);
        $verified = $gateway->verifyWebhookSignature($request);
        $eventId = $gateway->webhookEventId($request);

        $event = WebhookEvent::query()->firstOrCreate(
            ['provider' => $provider, 'event_id' => $eventId],
            ['payload' => $request->all(), 'verified' => $verified],
        );

        if (! $verified || $event->processed_at !== null) {
            return;
        }

        $reference = $gateway->paymentReferenceFromWebhook($request);
        $payment = $reference !== null
            ? Payment::query()->where('provider', $provider)->where('provider_reference', $reference)->first()
            : null;

        if ($payment === null) {
            $event->update(['processed_at' => now()]);

            return;
        }

        $result = $gateway->verify($reference);

        PaymentApiLog::query()->create([
            'order_id' => $payment->order_id,
            'payment_id' => $payment->id,
            'provider' => $provider,
            'action' => 'verify',
            'request_payload' => ['provider_reference' => $reference],
            'response_payload' => $result->rawResponse,
            'status_code' => 200,
        ]);

        if ($payment->status !== PaymentStatus::Pending) {
            $event->update(['processed_at' => now()]);

            return;
        }

        if ($result->status === PaymentStatus::Success) {
            SettlePaymentSuccess::run($payment);
        } elseif ($result->status === PaymentStatus::Failed) {
            MarkPaymentFailed::run($payment);
        }

        $event->update(['processed_at' => now()]);
    }
}
