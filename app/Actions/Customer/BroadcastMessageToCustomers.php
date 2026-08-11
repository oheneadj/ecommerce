<?php

/**
 * Kicks off a staff-composed broadcast (Email/SMS/in-app) to a set of customers.
 */

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Jobs\FanOutCustomerBroadcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolves the recipient query to a plain ID list once, then hands off to
 * a queued job — a synchronous "All customers" send could mean looping
 * over thousands of rows dispatching per-channel jobs inline, blocking
 * the admin's request for no reason. Returns the targeted count
 * immediately; actual delivery happens async, so the compose page can
 * only honestly report "queued for N customers", not "sent to N" —
 * per-channel success/failure isn't knowable synchronously.
 */
class BroadcastMessageToCustomers
{
    use AsAction;

    /**
     * @param  Builder<User>  $recipients
     * @param  array<int, string>  $channels
     */
    public function handle(Builder $recipients, string $subject, string $message, array $channels): int
    {
        $customerIds = $recipients->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($customerIds === [] || $channels === []) {
            return 0;
        }

        FanOutCustomerBroadcast::dispatch($customerIds, $subject, $message, $channels);

        return count($customerIds);
    }
}
