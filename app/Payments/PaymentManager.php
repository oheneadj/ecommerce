<?php

/**
 * Resolves a configured payment provider driver at runtime.
 */

declare(strict_types=1);

namespace App\Payments;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Drivers\MoolreGateway;
use App\Payments\Drivers\PaystackGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Laravel Manager-pattern resolver for payment drivers, mirroring
 * App\Sms\SmsManager exactly. Historical/in-flight payments never go
 * through this default-resolution path — HandlePaymentWebhook,
 * VerifyPaymentWithGateway, and IssueProviderRefund all call
 * `driver($payment->provider)` explicitly, using whatever provider that
 * payment was actually created with, so an admin enabling/disabling
 * providers never affects a payment already in progress.
 */
class PaymentManager extends Manager
{
    /**
     * The first enabled provider by display order — used as the technical
     * fallback Laravel's Manager base class requires for a no-arg
     * `driver()` call, and reused by CheckoutPage as the pre-selected
     * radio option, so "which provider is the default" lives in one
     * place. Falls back to `config('payments.default')` only when no
     * provider has been enabled yet (a fresh deployment before the Super
     * Admin has visited Payment Providers).
     *
     * Queried via the query builder rather than the PaymentProviderSetting
     * model — Larastan infers the model's `provider` enum cast as always
     * non-null regardless of the column's actual state, which would make
     * a `??` fallback here look dead to static analysis even though it
     * isn't.
     */
    public function getDefaultDriver(): string
    {
        return DB::table('payment_provider_settings')
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->value('provider')
            ?? $this->config->get('payments.default');
    }

    /**
     * Build the Moolre payment driver.
     */
    protected function createMoolreDriver(): PaymentGateway
    {
        $config = $this->config->get('payments.providers.moolre', []);

        return new MoolreGateway(
            apiKey: $config['api_key'] ?? throw new InvalidArgumentException('Moolre payment API key is not configured.'),
            webhookSecret: $config['webhook_secret'] ?? throw new InvalidArgumentException('Moolre webhook secret is not configured.'),
        );
    }

    /**
     * Build the Paystack payment driver.
     */
    protected function createPaystackDriver(): PaymentGateway
    {
        $config = $this->config->get('payments.providers.paystack', []);

        return new PaystackGateway(
            secretKey: $config['secret_key'] ?? throw new InvalidArgumentException('Paystack secret key is not configured.'),
        );
    }
}
