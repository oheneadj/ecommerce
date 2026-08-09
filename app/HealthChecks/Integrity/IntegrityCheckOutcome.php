<?php

/**
 * The result of running a single Tier 3 integrity check.
 */

declare(strict_types=1);

namespace App\HealthChecks\Integrity;

class IntegrityCheckOutcome
{
    /**
     * @param  array<int, int>  $sampleIds
     */
    public function __construct(
        public readonly int $violationCount,
        public readonly array $sampleIds = [],
    ) {}

    public static function clean(): self
    {
        return new self(0);
    }

    /**
     * @param  array<int, int>  $allViolatingIds
     */
    public static function violations(array $allViolatingIds): self
    {
        return new self(count($allViolatingIds), array_slice($allViolatingIds, 0, 5));
    }
}
