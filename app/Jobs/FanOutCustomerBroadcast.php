<?php

/**
 * Delivers a staff-composed broadcast to a batch of customers across whichever channels were selected.
 */

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Customer\SendEmailToCustomer;
use App\Actions\Customer\SendSmsToCustomer;
use App\Models\User;
use App\Notifications\CustomerBroadcastNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Chunked rather than loaded all at once — "all customers" could mean
 * thousands of rows. Reuses the same `SendEmailToCustomer`/
 * `SendSmsToCustomer` Actions the single/bulk Customers-table actions
 * already use, so there's one delivery path per channel rather than a
 * second one built here; a customer missing the contact method for a
 * selected channel is silently skipped for that channel (same as the
 * existing bulk actions' "skipped" counting), not a job failure.
 */
class FanOutCustomerBroadcast implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    /**
     * @param  array<int, int>  $customerIds
     * @param  array<int, string>  $channels
     */
    public function __construct(
        private readonly array $customerIds,
        private readonly string $subject,
        private readonly string $message,
        private readonly array $channels,
    ) {
        $this->onQueue('processing');
    }

    public function handle(): void
    {
        User::query()->whereIn('id', $this->customerIds)->chunk(200, function ($customers): void {
            foreach ($customers as $customer) {
                if (in_array('email', $this->channels, true) && $customer->email !== null) {
                    SendEmailToCustomer::run($customer, $this->subject, $this->message);
                }

                if (in_array('sms', $this->channels, true) && $customer->phone !== null) {
                    SendSmsToCustomer::run($customer, $this->message);
                }

                if (in_array('database', $this->channels, true)) {
                    $customer->notify(new CustomerBroadcastNotification($this->subject, $this->message));
                }
            }
        });
    }

    public function failed(Throwable $exception): void
    {
        Log::error('FanOutCustomerBroadcast failed permanently', [
            'customer_count' => count($this->customerIds),
            'exception' => $exception->getMessage(),
        ]);
    }
}
