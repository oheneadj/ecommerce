<?php

/**
 * A code-level rule CI can genuinely enforce (docs/TASK-system-health-
 * checks.md Step 6.3) — money in this app is always integer minor units
 * (CLAUDE.md §13), never a `decimal` column. A `decimal` column sailing
 * through review would silently reintroduce float rounding error into a
 * financial figure, undetectable by any runtime health check.
 *
 * Deliberately narrow in scope: a fully generic "every unsignedBigInteger
 * should be a real FK" or "every externally-exposed table needs a ulid"
 * linter would need to know per-column intent (a plain integer count vs.
 * an unconstrained ID looks identical to static analysis) and would be
 * more false-positive-prone than useful — those are better caught by the
 * runtime ForeignKeysAreEnforced health check and code review.
 */

declare(strict_types=1);

namespace Tests\Feature\Health;

use PHPUnit\Framework\TestCase;

class MigrationLintingTest extends TestCase
{
    public function test_no_migration_declares_a_decimal_column(): void
    {
        $offenders = [];

        $migrationsPath = dirname(__DIR__, 3).'/database/migrations/*.php';

        foreach (glob($migrationsPath) as $path) {
            $contents = file_get_contents($path);

            if ($contents !== false && preg_match('/->decimal\(/', $contents)) {
                $offenders[] = basename($path);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These migrations declare a `decimal` column — money must be an integer minor-unit column (CLAUDE.md §13): '.implode(', ', $offenders),
        );
    }
}
