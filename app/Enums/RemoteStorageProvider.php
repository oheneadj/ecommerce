<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * A remote storage destination the Super Admin can activate for backups
 * from Store Settings. Mirrors App\Enums\SmsProvider exactly — adding a
 * new destination still means a new Flysystem disk registration + a
 * config/filesystems.php entry (never an Action change) plus a new case
 * here — the enum only gates the admin-facing choice, not disk
 * resolution itself.
 */
enum RemoteStorageProvider: string implements HasLabel
{
    case GoogleDrive = 'google_drive';

    public function label(): string
    {
        return match ($this) {
            self::GoogleDrive => 'Google Drive',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Which Flysystem disk (config/filesystems.php) this provider backs.
     */
    public function disk(): string
    {
        return match ($this) {
            self::GoogleDrive => 'gdrive',
        };
    }

    /**
     * Presence-only check (never a live API call) — mirrors
     * SmsProvider::hasCredentialsConfigured() exactly.
     */
    public function hasCredentialsConfigured(): bool
    {
        $credentials = (array) config("filesystems.disks.{$this->disk()}", []);

        return collect($credentials)
            ->except(['driver'])
            ->filter(fn ($value) => filled($value))
            ->isNotEmpty();
    }
}
