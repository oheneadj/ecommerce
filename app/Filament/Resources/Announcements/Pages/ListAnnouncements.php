<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * List-records page for the Announcements resource.
 */
class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    /**
     * Adds the "create" header action to the list page.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
