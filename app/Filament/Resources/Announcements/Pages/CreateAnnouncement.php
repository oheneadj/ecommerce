<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

/**
 * Create-record page for the Announcements resource.
 */
class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;
}
