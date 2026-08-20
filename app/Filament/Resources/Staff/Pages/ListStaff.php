<?php

declare(strict_types=1);

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * List-records page for the Staff resource.
 */
class ListStaff extends ListRecords
{
    protected static string $resource = StaffResource::class;

    /**
     * Adds the "invite staff member" header action to the list page.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Invite staff member'),
        ];
    }
}
