<?php

/**
 * Thrown when an SMS broadcast targets more customers than the configured cap.
 */

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Every SMS costs real money — this refuses to queue a broadcast past
 * `config('sms.broadcast_max_recipients')` rather than letting a single
 * "All customers" click fan out an unbounded, unbudgeted bill.
 */
class BroadcastRecipientLimitExceededException extends Exception
{
    public function __construct(int $recipientCount, int $limit)
    {
        parent::__construct("This SMS broadcast would reach {$recipientCount} customers, over the {$limit}-recipient limit. Narrow the audience or split it into smaller batches.");
    }
}
