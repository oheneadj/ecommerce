<?php

/**
 * Same email-verification notification Laravel ships, sent through the queue.
 */

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The stock `VerifyEmail` notification doesn't implement `ShouldQueue`, so
 * registration (and the resend button) blocked on the mail transport's
 * full round trip — inconsistent with this app's rule that external calls
 * must never block the request/response cycle. Everything else (the
 * verification URL, mail content) is inherited unchanged from the parent;
 * only queueing is added here.
 */
class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct()
    {
        $this->onQueue('emails');
    }

    /**
     * Exhausted retries — the customer never received their verification
     * email. Logged so a delivery outage is visible, not silently dropped.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Email verification notification failed permanently', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
