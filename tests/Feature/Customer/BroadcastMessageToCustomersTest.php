<?php

/**
 * Covers BroadcastMessageToCustomers + FanOutCustomerBroadcast — the
 * queued fan-out of a staff-composed broadcast across Email/SMS/in-app.
 */

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Actions\Customer\BroadcastMessageToCustomers;
use App\Exceptions\BroadcastRateLimitedException;
use App\Exceptions\BroadcastRecipientLimitExceededException;
use App\Jobs\FanOutCustomerBroadcast;
use App\Jobs\SendCustomerEmail;
use App\Jobs\SendCustomerSms;
use App\Models\User;
use App\Notifications\CustomerBroadcastNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class BroadcastMessageToCustomersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_one_fan_out_job_with_the_targeted_customer_ids(): void
    {
        Bus::fake();

        $customers = User::factory()->count(3)->create();

        $count = BroadcastMessageToCustomers::run(
            User::query()->whereIn('id', $customers->pluck('id')),
            'Sale!',
            'Everything is 20% off.',
            ['email'],
        );

        $this->assertSame(3, $count);
        Bus::assertDispatched(FanOutCustomerBroadcast::class);
    }

    /**
     * Regression: a single job covering the whole targeted batch meant a
     * mid-batch retry (a transient DB error, a failed dispatch call)
     * reprocessed every customer already notified earlier in that same
     * job, not just the ones after the failure point. Chunking into
     * multiple jobs bounds a retry's blast radius to one chunk (200
     * customers) instead of the entire broadcast.
     */
    public function test_a_batch_larger_than_the_chunk_size_dispatches_multiple_fan_out_jobs(): void
    {
        Bus::fake();

        $customers = User::factory()->count(250)->create();

        $count = BroadcastMessageToCustomers::run(
            User::query()->whereIn('id', $customers->pluck('id')),
            'Sale!',
            'Everything is 20% off.',
            ['email'],
        );

        $this->assertSame(250, $count);
        Bus::assertDispatchedTimes(FanOutCustomerBroadcast::class, 2);
    }

    public function test_no_job_is_dispatched_when_no_customers_match(): void
    {
        Bus::fake();

        $count = BroadcastMessageToCustomers::run(
            User::query()->whereRaw('1 = 0'),
            'Sale!',
            'Everything is 20% off.',
            ['email'],
        );

        $this->assertSame(0, $count);
        Bus::assertNotDispatched(FanOutCustomerBroadcast::class);
    }

    public function test_no_job_is_dispatched_when_no_channel_is_selected(): void
    {
        Bus::fake();

        $customer = User::factory()->create();

        $count = BroadcastMessageToCustomers::run(
            User::query()->whereKey($customer->id),
            'Sale!',
            'Everything is 20% off.',
            [],
        );

        $this->assertSame(0, $count);
        Bus::assertNotDispatched(FanOutCustomerBroadcast::class);
    }

    public function test_fan_out_dispatches_email_and_sms_jobs_for_the_selected_channels(): void
    {
        Bus::fake([SendCustomerEmail::class, SendCustomerSms::class]);

        $customer = User::factory()->create(['email' => 'has@example.com', 'phone' => '0551234567']);

        (new FanOutCustomerBroadcast([$customer->id], 'Sale!', 'Everything is 20% off.', ['email', 'sms']))->handle();

        Bus::assertDispatched(SendCustomerEmail::class);
        Bus::assertDispatched(SendCustomerSms::class);
    }

    public function test_fan_out_skips_email_for_a_customer_with_none_on_file(): void
    {
        Bus::fake([SendCustomerEmail::class]);

        $customer = User::factory()->create(['email' => null]);

        (new FanOutCustomerBroadcast([$customer->id], 'Sale!', 'Everything is 20% off.', ['email']))->handle();

        Bus::assertNotDispatched(SendCustomerEmail::class);
    }

    public function test_fan_out_skips_sms_for_a_customer_with_no_phone_on_file(): void
    {
        Bus::fake([SendCustomerSms::class]);

        $customer = User::factory()->create(['phone' => null]);

        (new FanOutCustomerBroadcast([$customer->id], 'Sale!', 'Everything is 20% off.', ['sms']))->handle();

        Bus::assertNotDispatched(SendCustomerSms::class);
    }

    public function test_fan_out_sends_the_database_notification_when_selected(): void
    {
        Notification::fake();

        $customer = User::factory()->create();

        (new FanOutCustomerBroadcast([$customer->id], 'Sale!', 'Everything is 20% off.', ['database']))->handle();

        Notification::assertSentTo($customer, CustomerBroadcastNotification::class);
    }

    public function test_an_sms_broadcast_over_the_recipient_cap_is_rejected(): void
    {
        Bus::fake();
        config(['sms.broadcast_max_recipients' => 2]);
        $customers = User::factory()->count(3)->create();

        $this->expectException(BroadcastRecipientLimitExceededException::class);

        BroadcastMessageToCustomers::run(
            User::query()->whereIn('id', $customers->pluck('id')),
            'Sale!',
            'Everything is 20% off.',
            ['sms'],
        );
    }

    public function test_an_email_only_broadcast_is_not_subject_to_the_sms_recipient_cap(): void
    {
        Bus::fake();
        config(['sms.broadcast_max_recipients' => 2]);
        $customers = User::factory()->count(3)->create();

        $count = BroadcastMessageToCustomers::run(
            User::query()->whereIn('id', $customers->pluck('id')),
            'Sale!',
            'Everything is 20% off.',
            ['email'],
        );

        $this->assertSame(3, $count);
        Bus::assertDispatched(FanOutCustomerBroadcast::class);
    }

    public function test_a_rejected_over_cap_broadcast_never_dispatches_the_fan_out_job(): void
    {
        Bus::fake();
        config(['sms.broadcast_max_recipients' => 1]);
        $customers = User::factory()->count(2)->create();

        try {
            BroadcastMessageToCustomers::run(
                User::query()->whereIn('id', $customers->pluck('id')),
                'Sale!',
                'Everything is 20% off.',
                ['sms'],
            );
        } catch (BroadcastRecipientLimitExceededException) {
            // expected
        }

        Bus::assertNotDispatched(FanOutCustomerBroadcast::class);
    }

    public function test_an_admin_sending_too_many_broadcasts_is_rate_limited(): void
    {
        Bus::fake();
        $customer = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            BroadcastMessageToCustomers::run(
                User::query()->whereKey($customer->id),
                'Sale!',
                'Everything is 20% off.',
                ['email'],
                actingAdminId: 99,
            );
        }

        $this->expectException(BroadcastRateLimitedException::class);

        BroadcastMessageToCustomers::run(
            User::query()->whereKey($customer->id),
            'Sale!',
            'Everything is 20% off.',
            ['email'],
            actingAdminId: 99,
        );
    }

    public function test_the_broadcast_rate_limit_is_scoped_per_admin(): void
    {
        Bus::fake();
        RateLimiter::clear('customer-broadcast:99');
        $customer = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            BroadcastMessageToCustomers::run(
                User::query()->whereKey($customer->id),
                'Sale!',
                'Everything is 20% off.',
                ['email'],
                actingAdminId: 99,
            );
        }

        // A different admin is unaffected by admin 99's rate limit.
        $count = BroadcastMessageToCustomers::run(
            User::query()->whereKey($customer->id),
            'Sale!',
            'Everything is 20% off.',
            ['email'],
            actingAdminId: 100,
        );

        $this->assertSame(1, $count);
    }

    public function test_fan_out_only_dispatches_channels_that_were_actually_selected(): void
    {
        Bus::fake([SendCustomerEmail::class, SendCustomerSms::class]);
        Notification::fake();

        $customer = User::factory()->create(['email' => 'has@example.com', 'phone' => '0551234567']);

        (new FanOutCustomerBroadcast([$customer->id], 'Sale!', 'Everything is 20% off.', ['email']))->handle();

        Bus::assertDispatched(SendCustomerEmail::class);
        Bus::assertNotDispatched(SendCustomerSms::class);
        Notification::assertNotSentTo($customer, CustomerBroadcastNotification::class);
    }
}
