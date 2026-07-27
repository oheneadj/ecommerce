<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Actions\Catalog\CreateProduct as CreateProductAction;
use App\Exceptions\ProductRequiresVariantException;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function handleRecordCreation(array $data): Product
    {
        $variants = $data['variants'] ?? [];
        unset($data['variants']);

        try {
            return CreateProductAction::run($data, $variants);
        } catch (ProductRequiresVariantException $e) {
            Notification::make()->title('Cannot create product')->body($e->getMessage())->danger()->send();

            throw new Halt;
        }
    }
}
