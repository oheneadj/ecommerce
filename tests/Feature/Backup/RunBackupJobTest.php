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
use App\Jobs\RunBackupJob;
use App\Models\BackupRun;
use App\Models\StoreSetting;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RunBackupJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fails_immediately_when_no_remote_storage_is_configured(): void
    {
        config(['filesystems.disks.gdrive.serviceAccountJson' => null, 'filesystems.disks.gdrive.folder' => null]);

        (new RunBackupJob)->handle();

        $run = BackupRun::query()->sole();
        $this->assertSame(BackupStatus::Failed, $run->status);
        $this->assertSame('RemoteStorageNotConfigured', $run->error_message);
    }

    public function test_it_never_calls_artisan_when_no_remote_storage_is_configured(): void
    {
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

    public function test_a_permanent_failure_marks_the_running_row_as_failed(): void
    {
        $run = BackupRun::factory()->running()->create();

        (new RunBackupJob)->failed(new Exception('boom'));

        $run->refresh();
        $this->assertSame(BackupStatus::Failed, $run->status);
        $this->assertSame(Exception::class, $run->error_message);
    }
}
