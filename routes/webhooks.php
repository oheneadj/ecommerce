<?php

declare(strict_types=1);

use App\Http\Controllers\Payment\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/payments/{provider}', [WebhookController::class, 'handle'])
    ->name('webhooks.payments');
