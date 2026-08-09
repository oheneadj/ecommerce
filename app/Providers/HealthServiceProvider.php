<?php

/**
 * Registers every health check this app exposes (docs/TASK-system-health-checks.md).
 */

declare(strict_types=1);

namespace App\Providers;

use App\HealthChecks\DatabaseEngineIsInnoDb;
use App\HealthChecks\ExpiredReservationsAreBeingReleased;
use App\HealthChecks\ForeignKeysAreEnforced;
use App\HealthChecks\PaymentProvidersConfigured;
use App\HealthChecks\PendingPaymentsAreBeingVerified;
use App\HealthChecks\SmsProviderConfigured;
use App\HealthChecks\StaticPagesHaveContent;
use App\HealthChecks\StorageIsWritableAndLinked;
use App\HealthChecks\StoreSettingsPopulated;
use App\HealthChecks\SuperAdminExists;
use App\HealthChecks\TransactionDurabilityEnabled;
use App\HealthChecks\TransactionIsolationLevelIsSafe;
use Illuminate\Support\ServiceProvider;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

/**
 * Tier 1 (config/schema) and Tier 2 (operational heartbeat) checks are
 * registered here and run on demand. Tier 3 (data integrity) checks are
 * deliberately NOT registered here — they run only via the nightly
 * scheduled command (routes/console.php), never on page load, since
 * they're full-table aggregate scans (docs/TASK-system-health-checks.md
 * Step 4).
 */
class HealthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Health::checks([
            DebugModeCheck::new(),
            EnvironmentCheck::new()->expectEnvironment('production'),
            DatabaseCheck::new(),
            ScheduleCheck::new(),
            QueueCheck::new(),
            UsedDiskSpaceCheck::new(),
            OptimizedAppCheck::new(),

            DatabaseEngineIsInnoDb::new(),
            TransactionDurabilityEnabled::new(),
            TransactionIsolationLevelIsSafe::new(),
            ForeignKeysAreEnforced::new(),
            PaymentProvidersConfigured::new(),
            SmsProviderConfigured::new(),
            StoreSettingsPopulated::new(),
            StaticPagesHaveContent::new(),
            SuperAdminExists::new(),
            StorageIsWritableAndLinked::new(),

            ExpiredReservationsAreBeingReleased::new(),
            PendingPaymentsAreBeingVerified::new(),
        ]);
    }
}
