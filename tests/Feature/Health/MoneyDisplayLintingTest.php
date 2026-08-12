<?php

/**
 * Companion to MigrationLintingTest — money is stored as integer minor
 * units (CLAUDE.md §13), so every Blade view rendering a money value must
 * go through `<x-money>` or a `HasFormattedMoney`-backed `*_formatted`
 * accessor, never inline `/ 100` division. A stray raw division in a view
 * either double-converts an already-formatted string or, worse, silently
 * reintroduces float display error — undetectable by any runtime check,
 * same reasoning as the migration linter.
 */

declare(strict_types=1);

namespace Tests\Feature\Health;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MoneyDisplayLintingTest extends TestCase
{
    public function test_no_blade_view_divides_a_money_value_by_100_inline(): void
    {
        $basePath = dirname(__DIR__, 3);

        // The canonical `<x-money>` component is the one legitimate place
        // this division happens — every other view renders money through
        // it or a `*_formatted` model accessor instead.
        $canonicalFormatter = $basePath.'/resources/views/components/money.blade.php';

        $offenders = [];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath.'/resources/views', FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getPathname(), '.blade.php')) {
                continue;
            }

            if ($file->getPathname() === $canonicalFormatter) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            if ($contents !== false && preg_match('/\/\s*100\b/', $contents)) {
                $offenders[] = str_replace($basePath.'/', '', $file->getPathname());
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'These views divide a value by 100 inline instead of using <x-money> or a *_formatted accessor: '.implode(', ', $offenders),
        );
    }
}
