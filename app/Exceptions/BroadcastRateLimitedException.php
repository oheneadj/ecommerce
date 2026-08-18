<?php

/**
 * Thrown when an admin sends too many customer broadcasts in a short window.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Separate from the per-broadcast recipient cap — this limits how often
 * an admin can trigger a broadcast at all, so a scripted/compromised
 * admin session can't queue many uncapped-but-large sends back to back.
 */
class BroadcastRateLimitedException extends Exception
{
    public function __construct(int $availableInSeconds)
    {
        $minutes = (int) ceil($availableInSeconds / 60);

        parent::__construct("Too many broadcasts sent recently. Try again in about {$minutes} minute(s).");
    }
}
