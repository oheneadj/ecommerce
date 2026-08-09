<?php

/**
 * The last known result of a single Tier 3 data-integrity check.
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row per check, updated in place by the nightly
 * `health:run-integrity-checks` command — this is a "last known result",
 * never a running history. The Filament health dashboard reads this table
 * directly rather than re-running the underlying aggregate query, since
 * these are full-table scans that must never run on page load
 * (docs/TASK-system-health-checks.md Step 4).
 *
 * @property int $id
 * @property string $check_name
 * @property string $severity
 * @property string $status
 * @property int $violation_count
 * @property array<int, int>|null $sample_ids
 * @property string|null $message
 * @property Carbon $ran_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['check_name', 'severity', 'status', 'violation_count', 'sample_ids', 'message', 'ran_at'])]
class IntegrityCheckResult extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sample_ids' => 'array',
            'ran_at' => 'datetime',
        ];
    }
}
