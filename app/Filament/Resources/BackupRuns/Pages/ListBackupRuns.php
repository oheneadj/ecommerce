<?php

declare(strict_types=1);

namespace App\Filament\Resources\BackupRuns\Pages;

use App\Filament\Resources\BackupRuns\BackupRunResource;
use Filament\Resources\Pages\ListRecords;

class ListBackupRuns extends ListRecords
{
    protected static string $resource = BackupRunResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
