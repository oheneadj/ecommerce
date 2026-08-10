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
 * page — the snooze is a single global timestamp
 * (`StoreSetting::health_alerts_snoozed_until`), deliberately simple: it
 * mutes the reminder for exactly 24 hours regardless of whether the
 * underlying failures change in the meantime, rather than tracking which
 * specific failures were acknowledged.
 */
class SendCriticalHealthAlert
{
    use AsAction;

    public function handle(): void
    {
        $failures = ListCriticalHealthFailures::run();

        if ($failures === []) {
            return;
        }

        $snoozedUntil = StoreSetting::current()->health_alerts_snoozed_until;

        if ($snoozedUntil !== null && $snoozedUntil->isFuture()) {
            return;
        }

        $notification = new CriticalHealthAlert($failures);

        foreach (StaffRecipients::forRole(UserRole::SuperAdmin->value) as $superAdmin) {
            SafeNotifier::send($superAdmin, $notification);
        }
    }
}
