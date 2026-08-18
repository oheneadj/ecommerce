<?php

/**
 * Notifies every Super Admin, once a day, for as long as a critical check is failing.
 */

declare(strict_types=1);

namespace App\Actions\Health;

use App\Enums\UserRole;
use App\Models\StoreSetting;
use App\Notifications\CriticalHealthAlert;
use App\Notifications\Support\SafeNotifier;
use App\Notifications\Support\StaffRecipients;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Scheduled daily (routes/console.php). Silent no-op when nothing is
 * failing, or when a Super Admin has snoozed alerts via the System Health
 * page for exactly the set of failures that were on screen at snooze time
 * (`StoreSetting::health_alerts_snoozed_failures`). A snooze only
 * suppresses the alert while every currently-failing check was already
 * part of what was snoozed — a genuinely new, unrelated failure (e.g.
 * payments breaking while an SMS-provider warning was snoozed) still
 * alerts immediately rather than waiting out the full 24-hour window.
 */
class SendCriticalHealthAlert
{
    use AsAction;

    /**
     * Sends the alert to every Super Admin, unless nothing is failing or
     * every currently-failing check was already covered by an active snooze.
     */
    public function handle(): void
    {
        $failures = ListCriticalHealthFailures::run();

        if ($failures === []) {
            return;
        }

        $settings = StoreSetting::current();
        $snoozedUntil = $settings->health_alerts_snoozed_until;
        $snoozedFailures = $settings->health_alerts_snoozed_failures ?? [];

        $isFullySnoozed = $snoozedUntil !== null
            && $snoozedUntil->isFuture()
            && array_diff($failures, $snoozedFailures) === [];

        if ($isFullySnoozed) {
            return;
        }

        $notification = new CriticalHealthAlert($failures);

        foreach (StaffRecipients::forRole(UserRole::SuperAdmin->value) as $superAdmin) {
            SafeNotifier::send($superAdmin, $notification);
        }
    }
}
