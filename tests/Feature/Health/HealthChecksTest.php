<?php

/**
 * Covers the custom Tier 1 (config/schema) and Tier 2 (operational
 * heartbeat) health checks (docs/TASK-system-health-checks.md).
 */

declare(strict_types=1);

namespace Tests\Feature\Health;

use App\Enums\BackupFrequency;
use App\Enums\BackupStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\StockReservationStatus;
use App\Enums\UserRole;
use App\HealthChecks\BackupIsRecent;
use App\HealthChecks\DatabaseEngineIsInnoDb;
use App\HealthChecks\ExpiredReservationsAreBeingReleased;
use App\HealthChecks\ForeignKeysAreEnforced;
use App\HealthChecks\PaymentProvidersConfigured;
use App\HealthChecks\PendingPaymentsAreBeingVerified;
use App\HealthChecks\SentryConfigured;
use App\HealthChecks\SmsProviderConfigured;
use App\HealthChecks\StaticPagesHaveContent;
use App\HealthChecks\StorageIsWritableAndLinked;
use App\HealthChecks\StoreSettingsPopulated;
use App\HealthChecks\SuperAdminExists;
use App\HealthChecks\TransactionDurabilityEnabled;
use App\HealthChecks\TransactionIsolationLevelIsSafe;
use App\Models\BackupRun;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProviderSetting;
use App\Models\StaticPage;
use App\Models\StockReservation;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Health\Enums\Status;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HealthChecksTest extends TestCase
{
    use RefreshDatabase;

    // Tests run against sqlite — these four checks are MySQL-only and must
    // report ok ("not applicable") rather than fail on any other driver.
    public function test_mysql_only_checks_are_not_applicable_on_a_non_mysql_connection(): void
    {
        $this->assertSame(Status::ok(), DatabaseEngineIsInnoDb::new()->run()->status);
        $this->assertSame(Status::ok(), TransactionDurabilityEnabled::new()->run()->status);
        $this->assertSame(Status::ok(), TransactionIsolationLevelIsSafe::new()->run()->status);
        $this->assertSame(Status::ok(), ForeignKeysAreEnforced::new()->run()->status);
    }

    public function test_payment_providers_configured_fails_when_no_provider_is_enabled(): void
    {
        $this->assertSame(Status::failed(), PaymentProvidersConfigured::new()->run()->status);
    }

    public function test_payment_providers_configured_fails_when_an_enabled_provider_has_no_credentials(): void
    {
        config(['payments.providers' => ['moolre' => ['api_key' => null]]]);
        PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Moolre, 'enabled' => true]);

        $this->assertSame(Status::failed(), PaymentProvidersConfigured::new()->run()->status);
    }

    public function test_payment_providers_configured_passes_when_every_enabled_provider_has_credentials(): void
    {
        config(['payments.providers' => ['moolre' => ['api_key' => 'secret']]]);
        PaymentProviderSetting::factory()->create(['provider' => PaymentProvider::Moolre, 'enabled' => true]);

        $this->assertSame(Status::ok(), PaymentProvidersConfigured::new()->run()->status);
    }

    public function test_sms_provider_configured_fails_with_no_credentials(): void
    {
        config(['sms.default' => 'moolre', 'sms.providers.moolre' => ['api_key' => null, 'sender_id' => null]]);

        $this->assertSame(Status::failed(), SmsProviderConfigured::new()->run()->status);
    }

    public function test_sentry_configured_warns_not_fails_with_no_dsn(): void
    {
        config(['sentry.dsn' => null]);

        // Deliberately a warning, not a failure — the app functions
        // correctly without Sentry, so this must never trip the
        // critical-failure gate the way SMS/payment credentials do.
        $this->assertSame(Status::warning(), SentryConfigured::new()->run()->status);
    }

    public function test_sentry_configured_passes_with_a_dsn_set(): void
    {
        config(['sentry.dsn' => 'https://example@o0.ingest.sentry.io/0']);

        $this->assertSame(Status::ok(), SentryConfigured::new()->run()->status);
    }

    public function test_store_settings_populated_warns_when_fields_are_missing(): void
    {
        StoreSetting::current()->update(['business_name' => null, 'contact_email' => null, 'contact_phone' => null, 'logo_path' => null]);

        $this->assertSame(Status::warning(), StoreSettingsPopulated::new()->run()->status);
    }

    public function test_store_settings_populated_passes_when_filled_in(): void
    {
        StoreSetting::current()->update([
            'business_name' => 'Acme',
            'contact_email' => 'hello@acme.test',
            'contact_phone' => '+233200000000',
            'logo_path' => 'logos/acme.png',
        ]);

        $this->assertSame(Status::ok(), StoreSettingsPopulated::new()->run()->status);
    }

    public function test_static_pages_have_content_warns_when_a_slug_is_missing(): void
    {
        $this->assertSame(Status::warning(), StaticPagesHaveContent::new()->run()->status);
    }

    public function test_static_pages_have_content_passes_once_every_slug_is_published_with_content(): void
    {
        foreach (['about', 'contact', 'terms', 'privacy-policy', 'refund-policy'] as $slug) {
            StaticPage::factory()->create([
                'slug' => $slug,
                'is_published' => true,
                'content' => str_repeat('Real content. ', 10),
            ]);
        }

        $this->assertSame(Status::ok(), StaticPagesHaveContent::new()->run()->status);
    }

    public function test_super_admin_exists_fails_when_none_do(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');

        $this->assertSame(Status::failed(), SuperAdminExists::new()->run()->status);
    }

    public function test_super_admin_exists_fails_gracefully_when_the_role_has_never_been_seeded(): void
    {
        // No Role::findOrCreate call at all — this must report "failed",
        // not throw. This check runs on every admin page load via the
        // admin bar's critical-alert item, so an uncaught exception here
        // would take down the whole panel, not just report a status.
        $this->assertSame(Status::failed(), SuperAdminExists::new()->run()->status);
    }

    public function test_super_admin_exists_passes_once_one_is_assigned(): void
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);

        $this->assertSame(Status::ok(), SuperAdminExists::new()->run()->status);
    }

    public function test_storage_is_writable_and_linked_passes_in_the_test_environment(): void
    {
        $this->assertSame(Status::ok(), StorageIsWritableAndLinked::new()->run()->status);
    }

    /**
     * A stray plain directory at public/storage (left over from a broken
     * deploy, or created before `storage:link` was ever run) used to pass
     * this check just as well as a real symlink, while every upload still
     * 404ed for visitors — the check now requires an actual symlink.
     */
    public function test_storage_is_writable_and_linked_fails_when_public_storage_is_a_plain_directory_not_a_symlink(): void
    {
        $tempPublicPath = sys_get_temp_dir().'/health-check-public-'.uniqid();
        mkdir($tempPublicPath.'/storage', recursive: true);
        app()->usePublicPath($tempPublicPath);

        try {
            $result = StorageIsWritableAndLinked::new()->run();

            $this->assertSame(Status::failed(), $result->status);
            $this->assertStringContainsString('not a symlink', $result->notificationMessage);
        } finally {
            app()->usePublicPath(base_path('public'));
            rmdir($tempPublicPath.'/storage');
            rmdir($tempPublicPath);
        }
    }

    public function test_storage_is_writable_and_linked_fails_when_the_symlink_points_somewhere_unexpected(): void
    {
        $tempPublicPath = sys_get_temp_dir().'/health-check-public-'.uniqid();
        $wrongTarget = sys_get_temp_dir().'/health-check-wrong-target-'.uniqid();
        mkdir($tempPublicPath, recursive: true);
        mkdir($wrongTarget, recursive: true);
        symlink($wrongTarget, $tempPublicPath.'/storage');
        app()->usePublicPath($tempPublicPath);

        try {
            $result = StorageIsWritableAndLinked::new()->run();

            $this->assertSame(Status::failed(), $result->status);
            $this->assertStringContainsString('points somewhere unexpected', $result->notificationMessage);
        } finally {
            app()->usePublicPath(base_path('public'));
            unlink($tempPublicPath.'/storage');
            rmdir($tempPublicPath);
            rmdir($wrongTarget);
        }
    }

    public function test_expired_reservations_are_being_released_fails_when_one_is_stuck(): void
    {
        StockReservation::factory()->create([
            'status' => StockReservationStatus::Active,
            'expires_at' => now()->subMinutes(20),
        ]);

        $this->assertSame(Status::failed(), ExpiredReservationsAreBeingReleased::new()->run()->status);
    }

    public function test_expired_reservations_are_being_released_passes_when_none_are_stuck(): void
    {
        StockReservation::factory()->create([
            'status' => StockReservationStatus::Active,
            'expires_at' => now()->addMinutes(20),
        ]);

        $this->assertSame(Status::ok(), ExpiredReservationsAreBeingReleased::new()->run()->status);
    }

    public function test_pending_payments_are_being_verified_fails_when_one_is_stuck(): void
    {
        $this->travelTo(now()->subMinutes(40));
        Payment::factory()->create([
            'order_id' => Order::factory(),
            'status' => PaymentStatus::Pending,
        ]);
        $this->travelBack();

        $this->assertSame(Status::failed(), PendingPaymentsAreBeingVerified::new()->run()->status);
    }

    public function test_pending_payments_are_being_verified_passes_when_none_are_stuck(): void
    {
        Payment::factory()->create([
            'order_id' => Order::factory(),
            'status' => PaymentStatus::Pending,
            'created_at' => now(),
        ]);

        $this->assertSame(Status::ok(), PendingPaymentsAreBeingVerified::new()->run()->status);
    }

    public function test_backup_is_recent_fails_when_auto_backup_is_disabled(): void
    {
        StoreSetting::current()->update(['backup_auto_enabled' => false]);

        $this->assertSame(Status::failed(), BackupIsRecent::new()->run()->status);
    }

    public function test_backup_is_recent_fails_when_no_backup_has_ever_succeeded(): void
    {
        StoreSetting::current()->update(['backup_auto_enabled' => true, 'backup_frequency' => BackupFrequency::Daily]);

        $this->assertSame(Status::failed(), BackupIsRecent::new()->run()->status);
    }

    public function test_backup_is_recent_fails_when_the_latest_success_is_too_old(): void
    {
        StoreSetting::current()->update(['backup_auto_enabled' => true, 'backup_frequency' => BackupFrequency::Daily]);
        BackupRun::factory()->create(['status' => BackupStatus::Success, 'completed_at' => now()->subDays(5)]);

        $this->assertSame(Status::failed(), BackupIsRecent::new()->run()->status);
    }

    public function test_backup_is_recent_passes_when_a_recent_backup_succeeded(): void
    {
        StoreSetting::current()->update(['backup_auto_enabled' => true, 'backup_frequency' => BackupFrequency::Daily]);
        BackupRun::factory()->create(['status' => BackupStatus::Success, 'completed_at' => now()->subHours(2)]);

        $this->assertSame(Status::ok(), BackupIsRecent::new()->run()->status);
    }
}
