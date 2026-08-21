<?php

use App\Actions\Backup\RunScheduledBackup;
use App\Actions\Cart\PruneStaleGuestCarts;
use App\Actions\Health\SendCriticalHealthAlert;
use App\Actions\Inventory\CheckLowStockLevels;
use App\Actions\Inventory\ReleaseExpiredReservations;
use App\Actions\Payment\VerifyPendingPayments;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => ReleaseExpiredReservations::run())
    ->everyMinute()
    ->name('release-expired-stock-reservations')
    ->withoutOverlapping();

Schedule::call(fn () => VerifyPendingPayments::run())
    ->everyTwoMinutes()
    ->name('verify-pending-payments')
    ->withoutOverlapping();

Schedule::call(fn () => CheckLowStockLevels::run())
    ->daily()
    ->name('check-low-stock-levels')
    ->withoutOverlapping();

Schedule::call(fn () => PruneStaleGuestCarts::run())
    ->daily()
    ->name('prune-stale-guest-carts')
    ->withoutOverlapping();

// Health check heartbeats (docs/TASK-system-health-checks.md Step 1) —
// without these, ScheduleCheck/QueueCheck report as permanently failing.
Schedule::command('health:schedule-check-heartbeat')
    ->everyMinute()
    ->name('health-schedule-check-heartbeat');

Schedule::command('health:queue-check-heartbeat')
    ->everyMinute()
    ->name('health-queue-check-heartbeat');

// Tier 3 data-integrity checks (Step 4) — full-table aggregate scans,
// deliberately never run on page load or on demand, only here, nightly.
// Kept as a dedicated command/table rather than Health::checks(), so an
// on-demand "re-run" of the Tier 1/2 checks can never accidentally
// trigger one of these full-table scans.
Schedule::command('health:run-integrity-checks')
    ->daily()
    ->name('run-nightly-integrity-checks');

// Reminds Super Admin once a day for as long as a critical check is
// failing — snoozable for 24 hours from the System Health page.
Schedule::call(fn () => SendCriticalHealthAlert::run())
    ->daily()
    ->name('send-critical-health-alert');

// Checks daily whether a backup is actually due (App\Actions\Backup\
// RunScheduledBackup itself no-ops unless auto-backup is enabled and
// the configured frequency's interval has elapsed since the last
// successful run — "weekly" is enforced there, not by this schedule).
Schedule::call(fn () => RunScheduledBackup::run())
    ->daily()
    ->name('run-scheduled-backup')
    ->withoutOverlapping();

// config/backup.php's cleanup strategy (keep-all-for-30-days, then
// daily/weekly/monthly thinning) was already fully configured but never
// actually invoked anywhere — every successful backup accumulated on the
// gdrive disk forever with nothing pruning it.
Schedule::command('backup:clean')
    ->daily()
    ->name('clean-old-backups')
    ->withoutOverlapping();

// Telescope records full request/query/job payloads to its own tables
// with no automatic expiry — left running in production (see
// TELESCOPE_ENABLED), this keeps it from growing unbounded. 72 hours is
// enough to debug something noticed a day or two later, without keeping
// data indefinitely.
Schedule::command('telescope:prune --hours=72')
    ->daily()
    ->name('prune-telescope-entries');
