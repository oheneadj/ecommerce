<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Pages;

use App\Filament\Resources\ShippingMethods\ShippingMethodResource;
use App\Models\ShippingMethod;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

/**
 * Edit-record page for the Shipping Methods resource.
 */
class EditShippingMethod extends EditRecord
{
    protected static string $resource = ShippingMethodResource::class;

    /**
     * Adds the "delete" header action to the edit page, blocked while any
     * shipment still references this method.
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (ShippingMethod $record): void {
                    // shipments.shipping_method_id is restrictOnDelete() at
                    // the DB level — without this check, deleting a method
                    // still referenced by a shipment throws an unhandled
                    // QueryException (a raw 500) instead of a clean,
                    // actionable message. Same pattern as EditCategory.
                    if ($record->shipments()->exists()) {
                        Notification::make()
                            ->title('Cannot delete shipping method')
                            ->body('This shipping method is still used by one or more shipments. Deactivate it instead of deleting it.')
                            ->danger()
                            ->send();

                        throw new Halt;
                    }
                }),
        ];
    }
}
