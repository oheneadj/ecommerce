<?php

/**
 * Resolves a configured payment provider driver at runtime.
 */

declare(strict_types=1);

namespace App\Payments;

use App\Models\StoreSetting;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Drivers\MoolreGateway;
use App\Payments\Drivers\PaystackGateway;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Laravel Manager-pattern resolver for payment drivers, mirroring
 * App\Sms\SmsManager exactly. Historical/in-flight payments never go
 * through this default-resolution path — HandlePaymentWebhook,
 * VerifyPaymentWithGateway, and IssueProviderRefund all call
 * `driver($payment->provider)` explicitly, using whatever provider that
 * payment was actually created with, so switching the active provider here
 * never affects a payment already in progress.
 */
class PaymentManager extends Manager
{
    /**
     * Read the Super Admin's chosen active provider from Store Settings,
     * falling back to `config('payments.default')` only for a fresh
     * deployment before that setting has ever been saved.
     */
    public function getDefaultDriver(): string
    {
        // Reads the raw un-cast column rather than the enum-typed property —
        // Larastan infers the enum cast as always non-null regardless of the
        // column's actual nullability, which would make the `??` fallback
        // below look dead to static analysis even though it isn't.
        return StoreSetting::current()->getRawOriginal('active_payment_provider')
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
