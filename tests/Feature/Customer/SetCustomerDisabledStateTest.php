<?php

/**
 * Covers SetCustomerDisabledState — disabling kills any active session and
 * blocks further login attempts; re-enabling is a plain flag flip with no
 * password reset (customers have no invite flow to resend).
 */

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Actions\Customer\SetCustomerDisabledState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class SetCustomerDisabledStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabling_sets_disabled_at(): void
    {
        $customer = User::factory()->create();

        SetCustomerDisabledState::run($customer, true);

        $this->assertNotNull($customer->fresh()->disabled_at);
    }

    public function test_disabling_deletes_the_customers_active_sessions(): void
    {
        $customer = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 'session-1',
            'user_id' => $customer->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode('data'),
            'last_activity' => time(),
        ]);

        SetCustomerDisabledState::run($customer, true);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $customer->id)->count());
    }

    public function test_enabling_clears_disabled_at(): void
    {
        $customer = User::factory()->create(['disabled_at' => now()]);

        SetCustomerDisabledState::run($customer, false);

        $this->assertNull($customer->fresh()->disabled_at);
    }

    /**
     * Unlike staff (SetStaffDisabledState), a customer's password is left
     * completely untouched on re-enable — there's no invite flow to
     * resend, and forcing a reset would be jarring for phone/Google-only
     * accounts that may have no password at all.
     */
    public function test_enabling_never_changes_the_password(): void
    {
        $customer = User::factory()->create(['disabled_at' => now()]);
        $originalPassword = $customer->password;

        SetCustomerDisabledState::run($customer, false);

        $this->assertSame($originalPassword, $customer->fresh()->password);
    }

    public function test_disabling_is_recorded_in_the_activity_log(): void
    {
        $customer = User::factory()->create();

        SetCustomerDisabledState::run($customer, true);

        $this->assertDatabaseHas((new Activity)->getTable(), [
            'subject_type' => User::class,
            'subject_id' => $customer->id,
        ]);
    }
}
