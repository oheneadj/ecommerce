<?php

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
