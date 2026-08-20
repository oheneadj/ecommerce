<?php

declare(strict_types=1);

namespace App\Filament\Resources\Brands\Pages;

use App\Filament\Resources\Brands\BrandResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/** Lists brands for the Brand resource. */
class ListBrands extends ListRecords
{
    protected static string $resource = BrandResource::class;

    /** Header actions shown on the brands list. */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
