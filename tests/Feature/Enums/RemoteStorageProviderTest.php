<?php

/**
 * Covers RemoteStorageProvider::hasCredentialsConfigured() — presence-only
 * check against the 'gdrive' Flysystem disk config, mirroring how
 * SmsProvider checks its own provider configs.
 */

declare(strict_types=1);

namespace Tests\Feature\Enums;

use App\Enums\RemoteStorageProvider;
use Tests\TestCase;

class RemoteStorageProviderTest extends TestCase
{
    public function test_google_drive_reports_unconfigured_when_no_credentials_are_set(): void
    {
        config([
            'filesystems.disks.gdrive.serviceAccountJson' => null,
            'filesystems.disks.gdrive.folder' => null,
        ]);

        $this->assertFalse(RemoteStorageProvider::GoogleDrive->hasCredentialsConfigured());
    }

    public function test_google_drive_reports_configured_once_credentials_are_set(): void
    {
        config([
            'filesystems.disks.gdrive.serviceAccountJson' => '/path/to/service-account.json',
            'filesystems.disks.gdrive.folder' => 'folder-id',
        ]);

        $this->assertTrue(RemoteStorageProvider::GoogleDrive->hasCredentialsConfigured());
    }

    public function test_disk_returns_the_gdrive_disk_name(): void
    {
        $this->assertSame('gdrive', RemoteStorageProvider::GoogleDrive->disk());
    }
}
