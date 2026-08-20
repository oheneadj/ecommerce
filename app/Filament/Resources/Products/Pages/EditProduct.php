<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Catalog\DeleteProduct;
use App\Actions\Catalog\DeleteProductImageFiles;
use App\Actions\Catalog\UpdateProduct;
use App\Enums\ProductStatus;
use App\Exceptions\ProductRequiresVariantException;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Product edit page — view-live link, delete/force-delete/restore header
 * actions, and update handling routed through UpdateProduct.
 */
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Registers the header actions for the edit page.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewLive')
                ->label('View live')
                ->icon('heroicon-o-eye')
                ->url(fn (Product $record): string => route('products.show', $record))
                ->openUrlInNewTab()
                ->visible(fn (Product $record): bool => $record->status === ProductStatus::Active && $record->variants()->exists()),
            DeleteAction::make()
                ->using(function (Product $record): bool {
                    DeleteProduct::run($record);

                    return true;
                }),
            ForceDeleteAction::make()
                ->before(fn (Product $record) => DeleteProductImageFiles::run($record)),
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
