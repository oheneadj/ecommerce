<?php

/**
 * Resolves the configured SMS provider driver at runtime.
 */

declare(strict_types=1);

namespace App\Sms;

use App\Models\StoreSetting;
use App\Sms\Contracts\SmsGateway;
use App\Sms\Drivers\GiantSms;
use App\Sms\Drivers\MoolreSms;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Laravel Manager-pattern resolver for SMS drivers, same approach Laravel uses
 * internally for Cache/Mail/Filesystem.
 */
class SmsManager extends Manager
{
    /**
     * Read the Super Admin's chosen active provider from Store Settings,
     * falling back to `config('sms.default')` only for a fresh deployment
     * before that setting has ever been saved.
     */
    public function getDefaultDriver(): string
    {
        // Reads the raw un-cast column rather than the enum-typed property —
        // Larastan infers the enum cast as always non-null regardless of the
        // column's actual nullability, which would make the `??` fallback
        // below look dead to static analysis even though it isn't.
        return StoreSetting::current()->getRawOriginal('active_sms_provider')
            ?? $this->config->get('sms.default');
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

    /**
     * Build the GiantSMS driver.
     */
    protected function createGiantsmsDriver(): SmsGateway
    {
        $config = $this->config->get('sms.providers.giantsms', []);

        return new GiantSms(
            apiToken: $config['api_token'] ?? throw new InvalidArgumentException('GiantSMS API token is not configured.'),
            senderId: $config['sender_id'] ?? throw new InvalidArgumentException('GiantSMS sender ID is not configured.'),
        );
    }
}
