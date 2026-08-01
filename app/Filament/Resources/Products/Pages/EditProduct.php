<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Catalog\DeleteProduct;
use App\Actions\Catalog\UpdateProduct;
use App\Exceptions\ProductRequiresVariantException;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (Product $record): bool {
                    DeleteProduct::run($record);

                    return true;
                }),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Product) {
            return parent::handleRecordUpdate($record, $data);
        }

        try {
            return UpdateProduct::run($record, $data);
        } catch (ProductRequiresVariantException $e) {
            Notification::make()->title('Cannot update product')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
