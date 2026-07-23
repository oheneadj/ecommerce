<?php

/**
 * Resolves the configured SMS provider driver at runtime.
 */

declare(strict_types=1);

namespace App\Sms;

use App\Sms\Contracts\SmsGateway;
use App\Sms\Drivers\MoolreSms;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Laravel Manager-pattern resolver for SMS drivers, same approach Laravel uses
 * internally for Cache/Mail/Filesystem — the active provider is read from
 * `config/sms.php`, never hardcoded in an Action.
 */
class SmsManager extends Manager
{
    /**
     * Get the default SMS driver name from config.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('sms.default');
    }

    /**
     * Build the Moolre SMS driver.
     */
    protected function createMoolreDriver(): SmsGateway
    {
        $config = $this->config->get('sms.providers.moolre', []);

        return new MoolreSms(
            apiKey: $config['api_key'] ?? throw new InvalidArgumentException('Moolre SMS API key is not configured.'),
            senderId: $config['sender_id'] ?? throw new InvalidArgumentException('Moolre SMS sender ID is not configured.'),
        );
    }
}
