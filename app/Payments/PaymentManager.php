<?php

/**
 * Resolves a configured payment provider driver at runtime.
 */

declare(strict_types=1);

namespace App\Payments;

use App\Payments\Contracts\PaymentGateway;
use App\Payments\Drivers\MoolreGateway;
use App\Payments\Drivers\PaystackGateway;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Laravel Manager-pattern resolver for payment drivers, mirroring
 * App\Sms\SmsManager exactly. The active provider is read from
 * `config/payments.php`, never hardcoded in an Action. Multiple providers
 * can be resolved side by side within a single request — the customer's
 * chosen channel (mobile money vs. card) determines which driver is used,
 * not a single global default.
 */
class PaymentManager extends Manager
{
    /**
     * Get the default payment driver name from config.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('payments.default');
    }

    /**
     * Resolve the driver mapped to a checkout channel (e.g. "mobile_money"
     * → moolre, "card" → paystack) via `config('payments.channels')`.
     */
    public function driverForChannel(string $channel): PaymentGateway
    {
        $provider = $this->config->get("payments.channels.{$channel}")
            ?? throw new InvalidArgumentException("No payment provider is configured for channel [{$channel}].");

        /** @var PaymentGateway */
        return $this->driver($provider);
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
