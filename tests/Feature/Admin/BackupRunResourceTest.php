<?php

/**
 * Covers the admin Backups page (App\Filament\Resources\BackupRuns) —
 * history table, the "Run backup now" header action, and the
 * password + typed-phrase-gated "Restore" row action.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Backup\RestoreFromBackup;
use App\Enums\BackupStatus;
use App\Enums\UserRole;
use App\Filament\Resources\BackupRuns\BackupRunResource;
use App\Filament\Resources\BackupRuns\Pages\ListBackupRuns;
use App\Jobs\RunBackupJob;
use App\Models\BackupRun;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackupRunResourceTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        Role::findOrCreate(UserRole::SuperAdmin->value, 'web');
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_super_admin_can_view_the_page(): void
    {
        $this->actingAs($this->superAdmin());

        Livewire::test(ListBackupRuns::class)->assertSuccessful();
    }

    public function test_admin_cannot_access_the_page(): void
    {
        $this->actingAs($this->admin());

        $this->assertFalse(BackupRunResource::canAccess());
    }

    public function test_run_now_dispatches_the_backup_job(): void
    {
        Queue::fake();
        $this->actingAs($this->superAdmin());

        Livewire::test(ListBackupRuns::class)->callAction(TestAction::make('runNow')->table());

        Queue::assertPushed(RunBackupJob::class);
    }

    public function test_restore_is_hidden_for_a_run_that_did_not_succeed(): void
    {
        $this->actingAs($this->superAdmin());
        $run = BackupRun::factory()->running()->create();

        Livewire::test(ListBackupRuns::class)
            ->assertActionHidden(TestAction::make('restore')->table($run));
    }

    public function test_restore_is_visible_for_a_successful_run(): void
    {
        $this->actingAs($this->superAdmin());
        $run = BackupRun::factory()->create(['status' => BackupStatus::Success]);

        Livewire::test(ListBackupRuns::class)
            ->assertActionVisible(TestAction::make('restore')->table($run));
    }

    public function test_restore_is_rejected_with_the_wrong_password(): void
    {
        $this->actingAs($this->superAdmin());
        $run = BackupRun::factory()->create(['status' => BackupStatus::Success, 'disk' => 'gdrive', 'remote_path' => 'x.zip']);
        $this->mock(RestoreFromBackup::class)->shouldNotReceive('handle');

        Livewire::test(ListBackupRuns::class)
            ->callAction(TestAction::make('restore')->table($run), data: [
                'password' => 'wrong-password',
                'confirmation' => 'RESTORE',
            ]);
    }

    public function test_restore_is_rejected_without_the_exact_confirmation_phrase(): void
    {
        $this->actingAs($this->superAdmin());
        $run = BackupRun::factory()->create(['status' => BackupStatus::Success, 'disk' => 'gdrive', 'remote_path' => 'x.zip']);
        $this->mock(RestoreFromBackup::class)->shouldNotReceive('handle');

        Livewire::test(ListBackupRuns::class)
            ->callAction(TestAction::make('restore')->table($run), data: [
                'password' => 'correct-password',
                'confirmation' => 'restore',
            ]);
    }

    public function test_restore_runs_with_the_correct_password_and_confirmation_phrase(): void
    {
        $this->actingAs($this->superAdmin());
        $run = BackupRun::factory()->create(['status' => BackupStatus::Success, 'disk' => 'gdrive', 'remote_path' => 'x.zip']);
        $this->mock(RestoreFromBackup::class)
            ->shouldReceive('handle')
            ->once()
            ->with(Mockery::on(fn (BackupRun $record): bool => $record->is($run)));

        Livewire::test(ListBackupRuns::class)
            ->callAction(TestAction::make('restore')->table($run), data: [
                'password' => 'correct-password',
                'confirmation' => 'RESTORE',
            ]);
    }
}
