<?php

/**
 * Delivers a staff-composed ad-hoc email to a customer.
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\CustomerMessage;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * `SendEmailToCustomer` validates the customer has an email on file
 * synchronously (so the admin gets immediate feedback if not), but the
 * actual SMTP call is dispatched here — per this project's "external API
 * calls must be queued" convention, same as every payment-gateway call.
 * Also what makes a bulk "send email to N customers" action return
 * instantly instead of blocking on N sequential SMTP calls.
 */
class SendCustomerEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 60];

    public function __construct(
        private readonly int $customerId,
        private readonly string $subject,
        private readonly string $body,
    ) {
        $this->onQueue('emails');
    }

    public function handle(): void
    {
        $customer = User::query()->find($this->customerId);

        if ($customer === null || $customer->email === null) {
            return;
        }

        Mail::to($customer->email)->send(new CustomerMessage($this->subject, $this->body));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendCustomerEmail failed permanently', [
            'customer_id' => $this->customerId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
