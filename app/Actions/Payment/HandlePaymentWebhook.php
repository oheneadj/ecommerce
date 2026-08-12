<?php

/**
 * Processes an inbound webhook notification from a payment provider.
 */

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Jobs\VerifyPaymentWithGateway;
use App\Models\Payment;
use App\Models\WebhookEvent;
use App\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
 * payment is always re-verified server-side via `PaymentGateway::verify()`.
 * That verification is dispatched to VerifyPaymentWithGateway rather than
 * called here — an external gateway call has no place blocking this
 * synchronous HTTP endpoint (this project's "external API calls must be
 * queued" convention); marking the event `processed_at` happens right
 * after dispatch, since receiving-and-queuing this event is itself the
 * idempotent action, independent of whether the queued verification later
 * succeeds or fails.
 */
class HandlePaymentWebhook
{
    use AsAction;

    /**
     * Providers' published webhook-sending IP addresses, for the soft
     * check below — only Paystack's are known/published today. A
     * provider with no entry here is simply skipped, never treated as
     * suspicious for lacking one.
     *
     * @var array<string, array<int, string>>
     */
    private const KNOWN_WEBHOOK_IPS = [
        'paystack' => ['52.31.139.75', '52.49.173.169', '52.214.14.220'],
    ];

    public function __construct(
        private readonly PaymentManager $payments,
    ) {}

    public function handle(Request $request, string $provider): void
    {
        $this->logIfIpIsUnexpected($request, $provider);

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

        if ($payment !== null) {
            VerifyPaymentWithGateway::dispatch($payment->id);
        }

        $event->update(['processed_at' => now()]);
    }

    /**
     * A soft, log-only defense-in-depth check — the HMAC signature check
     * above is what actually decides whether a webhook is genuine (nobody
     * can forge a valid signature without the secret key, regardless of
     * source IP), so an IP outside the published list is only ever
     * logged, never rejected. Provider IP lists can rotate without
     * notice; hard-blocking on this would risk silently dropping
     * legitimate payment confirmations for a check that isn't actually
     * load-bearing.
     */
    private function logIfIpIsUnexpected(Request $request, string $provider): void
    {
        $knownIps = self::KNOWN_WEBHOOK_IPS[$provider] ?? null;

        if ($knownIps === null) {
            return;
        }

        if (! in_array($request->ip(), $knownIps, true)) {
            Log::warning('Payment webhook received from an IP outside the provider\'s published list', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);
        }
    }
}
