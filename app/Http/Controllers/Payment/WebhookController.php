<?php

/**
 * HTTP entry point for inbound payment provider webhooks.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Payment;

use App\Actions\Payment\HandlePaymentWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin by design — signature verification, idempotency, and all business
 * logic live in HandlePaymentWebhook. Always responds 200 so the provider
 * doesn't retry a request we've already recorded, even when the payload
 * turned out to be unverified or unmatched to a known payment.
 *
 * That guarantee has to hold even when something throws — an unrecognized
 * `{provider}` (a typo'd webhook URL, a bot probing the path) makes
 * PaymentManager::driver() throw before HandlePaymentWebhook gets anywhere
 * near its own idempotency/verification logic. Any such failure is caught
 * here, logged, and still answered with 200 rather than surfacing as an
 * uncaught 500 that would make the provider retry indefinitely.
 */
class WebhookController extends Controller
{
    public function handle(Request $request, string $provider): Response
    {
        try {
            HandlePaymentWebhook::run($request, $provider);
        } catch (Throwable $e) {
            Log::error('Payment webhook handling failed', [
                'provider' => $provider,
                'exception' => $e->getMessage(),
            ]);
        }

        return response('', 200);
    }
}
