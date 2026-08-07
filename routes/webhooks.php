<?php

declare(strict_types=1);

use App\Http\Controllers\Payment\WebhookController;
use Illuminate\Support\Facades\Route;

// Generous per-IP cap — high enough to never interfere with real, bursty
// webhook delivery from a payment provider, but enough to block basic
// flooding (this is a fully public, unauthenticated endpoint; signature
// verification alone doesn't stop a request-volume attack, and every
// request — even one that ultimately fails signature verification —
// currently still writes a WebhookEvent row).
Route::post('/webhooks/payments/{provider}', [WebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.payments');
