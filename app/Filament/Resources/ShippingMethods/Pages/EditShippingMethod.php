<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Pages;

use App\Filament\Resources\ShippingMethods\ShippingMethodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * Edit-record page for the Shipping Methods resource.
 */
class EditShippingMethod extends EditRecord
{
    protected static string $resource = ShippingMethodResource::class;

    /**
     * Adds the "delete" header action to the edit page.
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
