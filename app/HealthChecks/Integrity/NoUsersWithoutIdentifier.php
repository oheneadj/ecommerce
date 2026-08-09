<?php

/**
 * Tier 3 (data integrity) — CRITICAL: every user must have at least one of
 * phone, email, or google_id.
 */

declare(strict_types=1);

namespace App\HealthChecks\Integrity;

use Illuminate\Support\Facades\DB;

/**
 * "At least one of N nullable columns" isn't expressible as a single DB
 * constraint (technical-design §4g) — a user with none of the three can't
 * be authenticated or contacted by any channel.
 */
class NoUsersWithoutIdentifier implements IntegrityCheck
{
    public function name(): string
    {
        return 'No users without an identifier';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function remediationHint(): string
    {
        return 'Investigate how these accounts were created without a phone, email, or google_id — they can never authenticate or be contacted.';
    }

    public function run(): IntegrityCheckOutcome
    {
        $ids = DB::table('users')
            ->whereNull('deleted_at')
            ->whereNull('phone')
            ->whereNull('email')
            ->whereNull('google_id')
            ->pluck('id')
            ->all();

        return $ids === [] ? IntegrityCheckOutcome::clean() : IntegrityCheckOutcome::violations($ids);
    }
}
