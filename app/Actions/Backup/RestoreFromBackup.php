<?php

/**
 * Restores the database and uploaded files from a completed backup.
 */

declare(strict_types=1);

namespace App\Actions\Backup;

use App\Enums\BackupStatus;
use App\Exceptions\BackupNotRestorableException;
use App\Models\BackupRun;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use ZipArchive;

/**
 * Genuinely destructive — overwrites the live database and every file on
 * the 'public'/'local' disks. Never called directly from a controller or
 * Livewire component; only from the Filament restore action
 * (App\Filament\Resources\BackupRuns), which gates it behind a re-typed
 * password and a literal confirmation phrase before this ever runs. This
 * Action re-validates the run is actually restorable on its own (never
 * trusts the UI alone), same "don't trust the button was clickable"
 * pattern used elsewhere in this app (e.g. OrderDetailPage::retryPayment()).
 */
class RestoreFromBackup
{
    use AsAction;

    public function handle(BackupRun $run): void
    {
        if ($run->status !== BackupStatus::Success || $run->disk === null || $run->remote_path === null) {
            throw new BackupNotRestorableException;
        }

        $workDir = storage_path('app/backup-temp/restore-'.$run->id);
        File::ensureDirectoryExists($workDir);

        try {
            $zipPath = $workDir.'/backup.zip';
            $this->downloadZip($run, $zipPath);

            $extractDir = $workDir.'/extracted';
            $this->extractZip($zipPath, $extractDir);

            $this->restoreDatabase($extractDir);
            $this->restoreFiles($extractDir);
        } finally {
            File::deleteDirectory($workDir);
        }
    }

    /**
     * Streamed rather than read into memory — a full database + files
     * backup can easily be hundreds of megabytes.
     */
    private function downloadZip(BackupRun $run, string $destination): void
    {
        $stream = Storage::disk($run->disk)->readStream($run->remote_path);

        if ($stream === null) {
            throw new RuntimeException("Backup file not found on disk [{$run->disk}]: {$run->remote_path}");
        }

        $local = fopen($destination, 'w');

        if ($local === false) {
            throw new RuntimeException("Could not open [{$destination}] for writing.");
        }

        stream_copy_to_stream($stream, $local);
        fclose($stream);
        fclose($local);
    }

    private function extractZip(string $zipPath, string $extractDir): void
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Could not open backup archive: {$zipPath}");
        }

        $password = config('backup.backup.password');

        if ($password !== null) {
            $zip->setPassword($password);
        }

        $this->rejectSymlinkEntries($zip);

        if (! $zip->extractTo($extractDir)) {
            $zip->close();

            throw new RuntimeException('Could not extract backup archive — wrong password or corrupt file.');
        }

        $zip->close();
    }

    /**
     * `ZipArchive::extractTo()` has rejected `..`-based path-traversal
     * entries at the extension level since PHP 7.1.8, but it still
     * extracts symlink entries verbatim without validating their target
     * (CVE-2014-9767) — a two-entry archive (one a symlink pointing
     * outside `$extractDir`, a second writing through that same relative
     * path) can write attacker-chosen content outside the intended
     * extraction directory once the symlink is later dereferenced. Backups
     * are normally self-generated, so this only matters if an attacker
     * already has write access to the Google Drive backup folder — but a
     * restore is destructive enough that failing closed here is worth it
     * regardless of how narrow that precondition is.
     */
    private function rejectSymlinkEntries(ZipArchive $zip): void
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zip->getExternalAttributesIndex($i, $opsys, $attributes);

            if ($opsys !== ZipArchive::OPSYS_UNIX) {
                continue;
            }

            $unixMode = $attributes >> 16;

            if (($unixMode & 0170000) === 0120000) {
                $zip->close();

                throw new RuntimeException('Backup archive contains a symlink entry — refusing to extract.');
            }
        }
    }

    /**
     * Imports the plain-SQL dump spatie/laravel-backup produces
     * (config/backup.php has database_dump_compressor set to null, so
     * this is never gzipped) via the `mysql` CLI client, piping the file
     * in over stdin — matches how spatie/db-dumper shells out for the
     * dump side. Credentials go through the MYSQL_PWD env var rather
     * than a CLI argument, so the password is never visible in a process
     * listing.
     */
    private function restoreDatabase(string $extractDir): void
    {
        $dumpFiles = File::glob("{$extractDir}/db-dumps/*.sql");
        $dumpFile = $dumpFiles[0] ?? null;

        if ($dumpFile === null) {
            throw new RuntimeException('No database dump found inside the backup archive.');
        }

        // Hardcoded to the 'mysql' connection, not config('database.default')
        // — the `mysql` CLI restore below is already MySQL-specific
        // (this app's own documented database platform, per
        // docs/infrastructure-deployment.md §1), so it must always read
        // real MySQL credentials regardless of which connection happens
        // to be active (e.g. sqlite in the test suite).
        $connection = config('database.connections.mysql');

        $result = Process::env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
            ->input(File::get($dumpFile))
            ->run([
                'mysql',
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--user='.$connection['username'],
                $connection['database'],
            ]);

        if ($result->failed()) {
            throw new RuntimeException('Database restore failed: '.$result->errorOutput());
        }
    }

    /**
     * Restores storage/app/public and storage/app/private from the zip's
     * portable 'app/public'/'app/private' structure (config/backup.php's
     * relative_path is set to storage_path() for exactly this reason) —
     * overwrites existing files, matching "restore replaces current
     * state" being the whole point of this action.
     */
    private function restoreFiles(string $extractDir): void
    {
        foreach (['app/public', 'app/private'] as $relativePath) {
            $source = "{$extractDir}/{$relativePath}";

            if (! File::isDirectory($source)) {
                continue;
            }

            File::copyDirectory($source, storage_path($relativePath));
        }
    }
}
