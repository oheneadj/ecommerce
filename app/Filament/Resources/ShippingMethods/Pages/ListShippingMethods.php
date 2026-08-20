<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Pages;

use App\Filament\Resources\ShippingMethods\ShippingMethodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * List-records page for the Shipping Methods resource.
 */
class ListShippingMethods extends ListRecords
{
    protected static string $resource = ShippingMethodResource::class;

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
