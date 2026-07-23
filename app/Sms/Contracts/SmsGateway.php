<?php

/**
 * Contract every SMS provider driver must implement.
 */

declare(strict_types=1);

namespace App\Sms\Contracts;

use App\Sms\SmsSendResult;

/**
 * Abstracts outbound SMS sending so Actions never call a vendor SDK directly —
 * adding or swapping a provider is a new driver class + config entry only.
 */
interface SmsGateway
{
    /**
     * Send a plain-text SMS message to the given phone number.
     */
    public function send(string $to, string $message): SmsSendResult;
}
