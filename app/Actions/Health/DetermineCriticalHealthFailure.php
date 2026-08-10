<?php

/**
 * Answers one question: is any CRITICAL-severity check currently failing?
 */

declare(strict_types=1);

namespace App\Actions\Health;

use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A thin boolean wrapper over ListCriticalHealthFailures — kept separate
 * because most callers (the admin bar, on every request) only need a
 * yes/no, not the list itself.
 */
class DetermineCriticalHealthFailure
{
    use AsAction;

    public function handle(): bool
    {
        return ListCriticalHealthFailures::run() !== [];
    }
}
