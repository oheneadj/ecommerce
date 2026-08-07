<?php

/**
 * Delivers a staff-composed ad-hoc SMS to a customer.
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Sms\Contracts\SmsGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `SendSmsToCustomer` validates the customer has a phone on file
 * synchronously (so the admin gets immediate feedback if not), but the
 * actual gateway call is dispatched here — per this project's "external
 * API calls must be queued" convention, same as every payment-gateway
 * call. Also what makes a bulk "send SMS to N customers" action return
 * instantly instead of blocking on N sequential gateway calls.
 */
class SendCustomerSms implements ShouldQueue
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
        private readonly string $message,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(SmsGateway $sms): void
    {
        $customer = User::query()->find($this->customerId);

        if ($customer === null || $customer->phone === null) {
            return;
        }

        $sms->send($customer->phone, $this->message);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('SendCustomerSms failed permanently', [
            'customer_id' => $this->customerId,
            'exception' => $exception->getMessage(),
        ]);
    }
}
