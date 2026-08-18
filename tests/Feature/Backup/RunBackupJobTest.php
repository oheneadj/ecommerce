<?php

/**
 * Covers App\Jobs\RunBackupJob — creates the BackupRun row and kicks off
 * spatie/laravel-backup's own commands. Actually running backup:run would
 * mean a real network call to Google Drive, so the artisan call itself is
 * mocked here (SystemCacheActionsTest already establishes this pattern
 * for this codebase); App\Listeners\RecordSuccessfulBackup /
 * RecordFailedBackup (covered separately in BackupEventListenersTest) are
 * what actually transition a run to Success/Failed once spatie's own
 * events fire for real.
 */

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Enums\BackupStatus;
use App\Enums\UserRole;
use App\Exceptions\RemoteStorageNotConfiguredException;
use App\Jobs\RunBackupJob;
use App\Models\BackupRun;
use App\Models\StoreSetting;
use App\Models\User;
use App\Notifications\BackupFailed;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RunBackupJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_immediately_when_no_remote_storage_is_configured(): void
    {
        Notification::fake();
        config(['filesystems.disks.gdrive.serviceAccountJson' => null, 'filesystems.disks.gdrive.folder' => null]);

        (new RunBackupJob)->handle();

        $run = BackupRun::query()->sole();
        $this->assertSame(BackupStatus::Failed, $run->status);
        $this->assertSame(RemoteStorageNotConfiguredException::class, $run->error_message);
    }

    /**
     * Never worth retrying (a missing credential doesn't fix itself), so
     * this alerts immediately rather than waiting for spatie's own
     * tries/retry_delay to be exhausted — unlike a real connection
     * failure partway through an actual backup:run attempt.
     */
    public function test_it_notifies_super_admins_immediately_when_no_remote_storage_is_configured(): void
    {
        Notification::fake();
        config(['filesystems.disks.gdrive.serviceAccountJson' => null, 'filesystems.disks.gdrive.folder' => null]);
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(UserRole::SuperAdmin->value);

        (new RunBackupJob)->handle();

        Notification::assertSentTo($superAdmin, BackupFailed::class);
    }

    public function test_it_never_calls_artisan_when_no_remote_storage_is_configured(): void
    {
        Notification::fake();
        config(['filesystems.disks.gdrive.serviceAccountJson' => null, 'filesystems.disks.gdrive.folder' => null]);

        Artisan::shouldReceive('call')->never();

        (new RunBackupJob)->handle();
    }

    public function test_it_creates_a_running_row_and_records_who_triggered_it(): void
    {
        config(['filesystems.disks.gdrive.serviceAccountJson' => '/tmp/key.json', 'filesystems.disks.gdrive.folder' => 'folder-id']);
        Artisan::shouldReceive('call')->twice()->andReturn(0);
        $user = User::factory()->create();

        (new RunBackupJob($user->id))->handle();

        $run = BackupRun::query()->sole();
        $this->assertSame(BackupStatus::Running, $run->status);
        $this->assertSame($user->id, $run->triggered_by);
    }

    public function test_it_applies_the_configured_retention_before_calling_backupclean(): void
    {
        config(['filesystems.disks.gdrive.serviceAccountJson' => '/tmp/key.json', 'filesystems.disks.gdrive.folder' => 'folder-id']);
        StoreSetting::current()->update(['backup_retention_days' => 45]);
        Artisan::shouldReceive('call')->twice()->andReturn(0);

        (new RunBackupJob)->handle();

        $this->assertSame(45, config('backup.cleanup.default_strategy.keep_all_backups_for_days'));
    }

    /**
     * A transient connection failure mid-upload must be retried by
     * spatie/laravel-backup internally (config/backup.php) before it's
     * ever treated as a real failure — see BackupEventListenersTest for
     * the "retries exhausted, now notify" side of this.
     */
    public function test_spatie_backup_retries_before_giving_up(): void
    {
        $this->assertSame(3, config('backup.backup.tries'));
        $this->assertSame(30, config('backup.backup.retry_delay'));
    }

    public function test_a_permanent_failure_marks_the_running_row_as_failed(): void
    {
        $run = BackupRun::factory()->running()->create();

        (new RunBackupJob)->failed(new Exception('boom'));

        $run->refresh();
        $this->assertSame(BackupStatus::Failed, $run->status);
        $this->assertSame(Exception::class, $run->error_message);
    }

    /**
     * The scheduler's own ->withoutOverlapping() only ever protected the
     * scheduled trigger against itself — a manual "Run now" click could
     * previously still race a scheduled dispatch onto the same queue and
     * both call backup:run concurrently. The job now holds a cache lock
     * for its entire run so a second, concurrent dispatch is a genuine
     * no-op rather than a second BackupRun row / a second backup:run call.
     */
    public function test_a_concurrent_dispatch_is_skipped_while_a_backup_is_already_running(): void
    {
        config(['filesystems.disks.gdrive.serviceAccountJson' => '/tmp/key.json', 'filesystems.disks.gdrive.folder' => 'folder-id']);
        Artisan::shouldReceive('call')->never();

        $lock = Cache::lock('backup-run-in-progress', 3600);
        $lock->get();

        try {
            (new RunBackupJob)->handle();
        } finally {
            $lock->release();
        }

        $this->assertDatabaseCount('backup_runs', 0);
    }

    public function test_the_lock_is_released_once_the_run_completes(): void
    {
        config(['filesystems.disks.gdrive.serviceAccountJson' => '/tmp/key.json', 'filesystems.disks.gdrive.folder' => 'folder-id']);
        Artisan::shouldReceive('call')->twice()->andReturn(0);

        (new RunBackupJob)->handle();

        $this->assertTrue(Cache::lock('backup-run-in-progress', 3600)->get());
    }
}
