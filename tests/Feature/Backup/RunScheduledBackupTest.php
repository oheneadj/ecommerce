<?php

/**
 * Covers App\Actions\Backup\RunScheduledBackup — the daily-scheduled guard
 * that decides whether an automatic backup is actually due.
 */

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Actions\Backup\RunScheduledBackup;
use App\Enums\BackupFrequency;
use App\Enums\BackupStatus;
use App\Jobs\RunBackupJob;
use App\Models\BackupRun;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RunScheduledBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_nothing_when_auto_backup_is_disabled(): void
    {
        Queue::fake();
        StoreSetting::current()->update(['backup_auto_enabled' => false, 'backup_frequency' => BackupFrequency::Daily]);

        RunScheduledBackup::run();

        Queue::assertNotPushed(RunBackupJob::class);
    }

    public function test_it_does_nothing_when_no_frequency_is_set(): void
    {
        Queue::fake();
        StoreSetting::current()->update(['backup_auto_enabled' => true, 'backup_frequency' => null]);

        RunScheduledBackup::run();

        Queue::assertNotPushed(RunBackupJob::class);
    }

    public function test_it_dispatches_when_no_backup_has_ever_run(): void
    {
        Queue::fake();
        StoreSetting::current()->update(['backup_auto_enabled' => true, 'backup_frequency' => BackupFrequency::Daily]);

        RunScheduledBackup::run();

        Queue::assertPushed(RunBackupJob::class);
    }

    public function test_daily_frequency_skips_when_the_last_success_was_today(): void
    {
        Queue::fake();
        StoreSetting::current()->update(['backup_auto_enabled' => true, 'backup_frequency' => BackupFrequency::Daily]);
        BackupRun::factory()->create(['status' => BackupStatus::Success, 'completed_at' => now()->subHours(2)]);

        RunScheduledBackup::run();

        Queue::assertNotPushed(RunBackupJob::class);
    }

    public function test_daily_frequency_dispatches_once_a_full_day_has_passed(): void
    {
        Queue::fake();
        StoreSetting::current()->update(['backup_auto_enabled' => true, 'backup_frequency' => BackupFrequency::Daily]);
        BackupRun::factory()->create(['status' => BackupStatus::Success, 'completed_at' => now()->subDays(2)]);

        RunScheduledBackup::run();

        Queue::assertPushed(RunBackupJob::class);
    }

    public function test_weekly_frequency_skips_before_seven_days_have_passed(): void
    {
        Queue::fake();
        StoreSetting::current()->update(['backup_auto_enabled' => true, 'backup_frequency' => BackupFrequency::Weekly]);
        BackupRun::factory()->create(['status' => BackupStatus::Success, 'completed_at' => now()->subDays(3)]);

        RunScheduledBackup::run();

        Queue::assertNotPushed(RunBackupJob::class);
    }

    public function test_weekly_frequency_dispatches_once_seven_days_have_passed(): void
    {
        Queue::fake();
        StoreSetting::current()->update(['backup_auto_enabled' => true, 'backup_frequency' => BackupFrequency::Weekly]);
        BackupRun::factory()->create(['status' => BackupStatus::Success, 'completed_at' => now()->subDays(8)]);

        RunScheduledBackup::run();

        Queue::assertPushed(RunBackupJob::class);
    }

    public function test_a_failed_run_does_not_count_as_the_last_successful_backup(): void
    {
        Queue::fake();
        StoreSetting::current()->update(['backup_auto_enabled' => true, 'backup_frequency' => BackupFrequency::Daily]);
        BackupRun::factory()->failed()->create(['completed_at' => now()->subMinutes(5)]);

        RunScheduledBackup::run();

        Queue::assertPushed(RunBackupJob::class);
    }
}
