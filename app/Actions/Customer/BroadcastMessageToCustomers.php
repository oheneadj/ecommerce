<?php

/**
 * Kicks off a staff-composed broadcast (Email/SMS/in-app) to a set of customers.
 */

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Exceptions\BroadcastRateLimitedException;
use App\Exceptions\BroadcastRecipientLimitExceededException;
use App\Jobs\FanOutCustomerBroadcast;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolves the recipient query to a plain ID list once, then hands off to
 * a queued job — a synchronous "All customers" send could mean looping
 * over thousands of rows dispatching per-channel jobs inline, blocking
 * the admin's request for no reason. Returns the targeted count
 * immediately; actual delivery happens async, so the compose page can
 * only honestly report "queued for N customers", not "sent to N" —
 * per-channel success/failure isn't knowable synchronously.
 *
 * Two cost/abuse guards, per CLAUDE.md §12's "cost-triggering actions need
 * their own rate limit": an SMS broadcast can't target more than
 * `config('sms.broadcast_max_recipients')` customers in one go (email/
 * in-app aren't capped — no per-send cost), and any admin is limited to 5
 * broadcasts per 10 minutes regardless of channel, so a scripted or
 * compromised admin session can't queue many large sends back to back.
 *
 * @throws BroadcastRecipientLimitExceededException when an SMS broadcast targets more customers than the configured cap
 * @throws BroadcastRateLimitedException when the acting admin has sent too many broadcasts too recently
 */
class BroadcastMessageToCustomers
{
    use AsAction;

    /**
     * @param  Builder<User>  $recipients
     * @param  array<int, string>  $channels
     */
    public function handle(Builder $recipients, string $subject, string $message, array $channels, ?int $actingAdminId = null): int
    {
        $rateLimitKey = "customer-broadcast:{$actingAdminId}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            throw new BroadcastRateLimitedException(RateLimiter::availableIn($rateLimitKey));
        }

        $customerIds = $recipients->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        if ($customerIds === [] || $channels === []) {
            return 0;
        }

        $limit = (int) config('sms.broadcast_max_recipients');

        if (in_array('sms', $channels, true) && count($customerIds) > $limit) {
            throw new BroadcastRecipientLimitExceededException(count($customerIds), $limit);
        }

        RateLimiter::hit($rateLimitKey, 600);

        // One job per chunk, not one job for the whole batch — if a job
        // throws partway through processing (a transient DB error, a
        // failed dispatch call) and gets retried, the retry only
        // reprocesses that chunk's customers, not every customer already
        // notified earlier in a single giant job. Chunk size matches
        // FanOutCustomerBroadcast's own internal chunk(200, ...) — no
        // reason for the dispatch-side and processing-side batch sizes to
        // drift apart.
        foreach (array_chunk($customerIds, 200) as $chunk) {
            FanOutCustomerBroadcast::dispatch($chunk, $subject, $message, $channels);
        }

        return count($customerIds);
    }
}
