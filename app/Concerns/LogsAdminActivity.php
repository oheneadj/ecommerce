<?php

/**
 * Shared activity-log configuration for admin-managed "key records" (FR-10.2).
 */

declare(strict_types=1);

namespace App\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Logs every fillable attribute, only when it actually changed, and skips
 * no-op saves entirely — so the activity log stays a signal of genuine
 * admin action, not noise from unrelated touches.
 */
trait LogsAdminActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName(class_basename(static::class));
    }
}
