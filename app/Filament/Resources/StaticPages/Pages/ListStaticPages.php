<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaticPages\Pages;

use App\Filament\Resources\StaticPages\StaticPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/** Lists static pages for the Static Page resource. */
class ListStaticPages extends ListRecords
{
    protected static string $resource = StaticPageResource::class;

    /** Header actions shown on the static pages list. */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
