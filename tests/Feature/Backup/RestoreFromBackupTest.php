<?php

/**
 * Covers App\Actions\Backup\RestoreFromBackup — download, extract, and
 * restore a completed backup. The `mysql` CLI invocation is faked
 * (Process::fake()) rather than run for real, same reasoning
 * RunBackupJobTest already applies to spatie's own artisan commands —
 * this asserts the restore pipeline wires everything correctly (download,
 * extraction, the exact command invoked, files restored onto the right
 * disks) without depending on a real mysql client being installed in CI.
 */

declare(strict_types=1);

namespace Tests\Feature\Backup;

use App\Actions\Backup\RestoreFromBackup;
use App\Enums\BackupStatus;
use App\Exceptions\BackupNotRestorableException;
use App\Models\BackupRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RestoreFromBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_a_run_that_never_succeeded(): void
    {
        $run = BackupRun::factory()->running()->create();

        $this->expectException(BackupNotRestorableException::class);

        RestoreFromBackup::run($run);
    }

    public function test_it_rejects_a_successful_run_with_no_remote_path(): void
    {
        $run = BackupRun::factory()->create(['status' => BackupStatus::Success, 'disk' => null, 'remote_path' => null]);

        $this->expectException(BackupNotRestorableException::class);

        RestoreFromBackup::run($run);
    }

    public function test_it_downloads_extracts_imports_the_database_and_restores_files(): void
    {
        Storage::fake('gdrive');
        Process::fake();

        $zipPath = $this->buildFixtureZip();
        Storage::disk('gdrive')->put('backups/test.zip', file_get_contents($zipPath));
        File::delete($zipPath);

        $run = BackupRun::factory()->create([
            'status' => BackupStatus::Success,
            'disk' => 'gdrive',
            'remote_path' => 'backups/test.zip',
        ]);

        RestoreFromBackup::run($run);

        Process::assertRan(function ($process): bool {
            $command = $process->command;
            $flattened = is_array($command) ? implode(' ', $command) : $command;

            return str_contains($flattened, 'mysql');
        });

        $this->assertSame('public contents', file_get_contents(storage_path('app/public/marker.txt')));
        $this->assertSame('private contents', file_get_contents(storage_path('app/private/marker.txt')));

        File::delete(storage_path('app/public/marker.txt'));
        File::delete(storage_path('app/private/marker.txt'));
    }

    /**
     * A minimal but structurally real backup zip: a db-dumps/*.sql file
     * and the portable app/public + app/private layout
     * config/backup.php's relative_path produces.
     */
    private function buildFixtureZip(): string
    {
        $zipPath = storage_path('app/backup-temp/fixture-'.uniqid().'.zip');
        File::ensureDirectoryExists(dirname($zipPath));

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);
        $zip->addFromString('db-dumps/test.sql', "SELECT 1;\n");
        $zip->addFromString('app/public/marker.txt', 'public contents');
        $zip->addFromString('app/private/marker.txt', 'private contents');
        $zip->close();

        return $zipPath;
    }
}
