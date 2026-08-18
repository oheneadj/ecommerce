<?php

/**
 * Covers the daily Super Admin notification for critical health failures,
 * and its snooze option (docs/TASK-system-health-checks.md Step 5.3
 * follow-up).
 */

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Actions\Health\ListCriticalHealthFailures;
use App\Actions\Health\SendCriticalHealthAlert;
use App\Enums\UserRole;
use App\Models\HealthAttestation;
use App\Models\StoreSetting;
use App\Models\User;
use App\Notifications\CriticalHealthAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Notification;
use Spatie\Health\Health as HealthManager;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SendCriticalHealthAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Notification::fake();
    }

    private function fakeEmptyCheckRegistry(): void
    {
        app()->instance(HealthManager::class, new class extends HealthManager
        {
            public function registeredChecks(): Collection
            {
                return collect();
            }
        });
        Facade::clearResolvedInstance(HealthManager::class);
    }

    public function test_notifies_every_super_admin_when_a_critical_check_is_failing(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        // Deliberately does not seed the required attestations, so
        // there's a genuine critical failure to notify about.
        SendCriticalHealthAlert::run();

        Notification::assertSentTo($superAdmin, CriticalHealthAlert::class);
    }

    public function test_the_alert_is_sent_via_sms_as_well_as_mail(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        SendCriticalHealthAlert::run();

        Notification::assertSentTo(
            $superAdmin,
            CriticalHealthAlert::class,
            fn ($notification, array $channels) => in_array('sms', $channels, true),
        );
    }

    public function test_does_not_notify_when_nothing_is_failing(): void
    {
        $this->fakeEmptyCheckRegistry();

        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        foreach (array_keys(HealthAttestation::REQUIRED) as $key) {
            HealthAttestation::factory()->create(['key' => $key, 'confirmed_by' => $superAdmin->id, 'confirmed_at' => now()]);
        }

        SendCriticalHealthAlert::run();

        Notification::assertNotSentTo($superAdmin, CriticalHealthAlert::class);
    }

    public function test_does_not_notify_while_alerts_are_snoozed_and_nothing_new_is_failing(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        StoreSetting::current()->update([
            'health_alerts_snoozed_until' => now()->addDay(),
            'health_alerts_snoozed_failures' => ListCriticalHealthFailures::run(),
        ]);

        SendCriticalHealthAlert::run();

        Notification::assertNotSentTo($superAdmin, CriticalHealthAlert::class);
    }

    public function test_notifies_again_once_the_snooze_expires(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        StoreSetting::current()->update([
            'health_alerts_snoozed_until' => now()->subMinute(),
            'health_alerts_snoozed_failures' => ListCriticalHealthFailures::run(),
        ]);

        SendCriticalHealthAlert::run();

        Notification::assertSentTo($superAdmin, CriticalHealthAlert::class);
    }

    /**
     * The core fix: a snooze must only suppress the exact failures it was
     * taken against — if what's currently failing isn't fully covered by
     * what was snoozed (a genuinely new, unrelated failure), it must still
     * alert immediately rather than waiting out the full 24 hours.
     */
    public function test_notifies_when_currently_failing_checks_are_not_fully_covered_by_the_snooze(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        // Snoozed against a check that isn't what's actually failing right
        // now, simulating a new/different failure appearing after the
        // snooze was taken.
        StoreSetting::current()->update([
            'health_alerts_snoozed_until' => now()->addDay(),
            'health_alerts_snoozed_failures' => ['Some other check entirely'],
        ]);

        SendCriticalHealthAlert::run();

        Notification::assertSentTo($superAdmin, CriticalHealthAlert::class);
    }

    public function test_a_legacy_snooze_with_no_recorded_failures_does_not_suppress_current_failures(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);
        // Simulates a snooze taken before this column existed / with no
        // failures recorded — must not silently suppress everything.
        StoreSetting::current()->update([
            'health_alerts_snoozed_until' => now()->addDay(),
            'health_alerts_snoozed_failures' => null,
        ]);

        SendCriticalHealthAlert::run();

        Notification::assertSentTo($superAdmin, CriticalHealthAlert::class);
    }

    public function test_does_not_notify_a_plain_admin(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $admin = User::factory()->create();
        $admin->assignRole(UserRole::Admin->value);

        SendCriticalHealthAlert::run();

        Notification::assertNotSentTo($admin, CriticalHealthAlert::class);
    }
}
