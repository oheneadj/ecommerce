<?php

/**
 * Covers App\Listeners\RecordSuccessfulBackup / RecordFailedBackup — how
 * spatie/laravel-backup's own events get turned into BackupRun rows and
 * (on failure) a Super Admin alert. Registered on these events in
 * AppServiceProvider::boot(), so dispatching the real event exercises the
 * full wiring, not just the listener class directly.
 */

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Enums\BackupStatus;
use App\Enums\UserRole;
use App\Models\BackupRun;
use App\Models\User;
use App\Notifications\BackupFailed;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Events\BackupHasFailed;
use Spatie\Backup\Events\BackupWasSuccessful;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackupEventListenersTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    public function test_a_successful_backup_transitions_the_running_run_to_success(): void
    {
        Storage::fake('gdrive');
        Storage::disk('gdrive')->put('TestApp/2026-01-01-00-00-00.zip', str_repeat('x', 1024));
        $run = BackupRun::factory()->running()->create();

        Event::dispatch(new BackupWasSuccessful(diskName: 'gdrive', backupName: 'TestApp'));

        $run->refresh();
        $this->assertSame(BackupStatus::Success, $run->status);
        $this->assertSame('gdrive', $run->disk);
        $this->assertNotNull($run->remote_path);
        $this->assertSame(1024, $run->size_bytes);
        $this->assertNotNull($run->completed_at);
    }

    public function test_a_failed_backup_transitions_the_running_run_to_failed(): void
    {
        Notification::fake();
        $this->superAdmin();
        $run = BackupRun::factory()->running()->create();

        Event::dispatch(new BackupHasFailed(exception: new Exception('boom')));

        $run->refresh();
        $this->assertSame(BackupStatus::Failed, $run->status);
        $this->assertSame(Exception::class, $run->error_message);
        $this->assertNotNull($run->completed_at);
    }

    public function test_a_failed_backup_notifies_every_super_admin(): void
    {
        Notification::fake();
        $superAdmin = $this->superAdmin();
        $nonAdmin = User::factory()->create();
        BackupRun::factory()->running()->create();

        Event::dispatch(new BackupHasFailed(exception: new Exception('boom')));

        Notification::assertSentTo($superAdmin, BackupFailed::class);
        Notification::assertNotSentTo($nonAdmin, BackupFailed::class);
    }

    public function test_a_successful_event_with_no_running_run_does_not_error(): void
    {
        Storage::fake('gdrive');

        Event::dispatch(new BackupWasSuccessful(diskName: 'gdrive', backupName: 'TestApp'));

        $this->assertSame(0, BackupRun::query()->count());
    }
}
