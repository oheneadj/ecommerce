<?php

/**
 * Contract for a Tier 3 (data integrity) check — a full-table aggregate
 * scan, run only nightly via RunIntegrityChecks, never on page load.
 */

declare(strict_types=1);

namespace App\HealthChecks\Integrity;

interface IntegrityCheck
{
    public function name(): string;

    /**
     * 'critical' or 'warning', matching docs/TASK-system-health-checks.md
     * Step 4's severity table.
     */
    public function severity(): string;

    /**
     * The exact remediation hint shown on the dashboard when this check
     * finds violations — never a vague description of the symptom.
     */
    public function remediationHint(): string;

    /**
     * @return IntegrityCheckOutcome the violation count and a sample of
     *                               affected record IDs, so the problem is actionable from the
     *                               dashboard alone.
     */
    public function run(): IntegrityCheckOutcome;
}
