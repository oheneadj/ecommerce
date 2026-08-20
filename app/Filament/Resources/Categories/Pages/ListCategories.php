<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/** Lists categories for the Category resource. */
class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    /** Header actions shown on the categories list. */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
