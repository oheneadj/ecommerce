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

/**
 * Product create page — strips the variants payload off the form data and
 * delegates creation (product + variants) to the CreateProduct action.
 */
class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Creates the product and its variants, surfacing a friendly error if
     * variant requirements aren't met.
     */
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
