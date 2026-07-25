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

/**
 * Thin by design — signature verification, idempotency, and all business
 * logic live in HandlePaymentWebhook. Always responds 200 so the provider
 * doesn't retry a request we've already recorded, even when the payload
 * turned out to be unverified or unmatched to a known payment.
 */
class WebhookController extends Controller
{
    public function handle(Request $request, string $provider): Response
    {
        HandlePaymentWebhook::run($request, $provider);

        return response('', 200);
    }
}
