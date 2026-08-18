<?php

/**
 * A single backup attempt — database + uploaded files together, in one
 * run (see App\Jobs\RunBackupJob).
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\BackupStatus;
use Database\Factories\BackupRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Not an append-only audit log like PaymentApiLog — this row is created
 * Pending/Running and later transitioned to Success/Failed by
 * App\Listeners\RecordSuccessfulBackup / RecordFailedBackup, so it's a
 * normal timestamped model. `error_message` is deliberately never the raw
 * exception message (same restraint App\Notifications\Support\SafeNotifier
 * already applies) — only the exception class name, since an underlying
 * SDK's exception text could in principle embed a credential.
 *
 * @property int $id
 * @property BackupStatus $status
 * @property int|null $triggered_by
 * @property string|null $disk
 * @property string|null $remote_path
 * @property int|null $size_bytes
 * @property string|null $error_message
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['status', 'triggered_by', 'disk', 'remote_path', 'size_bytes', 'error_message', 'started_at', 'completed_at'])]
class BackupRun extends Model
{
    /** @use HasFactory<BackupRunFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BackupStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The staff member who triggered a manual run — null for a scheduled one.
     *
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /**
     * Human-readable size for display — bytes are never shown raw in a
     * Blade/Filament view, same "format at the boundary" rule this
     * project already applies to money (CLAUDE.md §13).
     */
    public function sizeFormatted(): ?string
    {
        return $this->size_bytes === null ? null : self::formatBytes($this->size_bytes);
    }

    /**
     * Shared by sizeFormatted() above and App\Notifications\BackupSucceeded
     * (which has a size in bytes but no BackupRun instance handy at the
     * point it's constructed).
     */
    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 1).' '.$units[$unitIndex];
    }

    /**
     * Only ever expected to match one row at a time — App\Jobs\RunBackupJob
     * holds a cache lock for its entire run, so a second dispatch (scheduled
     * or manual) never gets far enough to create a second Running row.
     *
     * @param  Builder<BackupRun>  $query
     * @return Builder<BackupRun>
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', BackupStatus::Running);
    }

    /**
     * @param  Builder<BackupRun>  $query
     * @return Builder<BackupRun>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', BackupStatus::Success);
    }
}
