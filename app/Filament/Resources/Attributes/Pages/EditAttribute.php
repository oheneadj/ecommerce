<?php

declare(strict_types=1);

namespace App\Filament\Resources\Attributes\Pages;

use App\Filament\Resources\Attributes\AttributeResource;
use App\Models\Attribute;
use App\Models\ProductVariant;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttribute extends EditRecord
{
    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->requiresConfirmation()
                // attribute_terms/attribute_product/product_variant_attribute_term
                // all cascadeOnDelete() — deleting an attribute silently
                // strips it from every product/variant using it. Spelling
                // out the actual impact here (not just a generic "are you
                // sure?") is the only thing standing between an admin and
                // an irreversible mistake on a live catalog.
                ->modalDescription(function (Attribute $record): string {
                    $productCount = $record->products()->count();
                    $variantCount = ProductVariant::query()
                        ->whereHas('attributeTerms', fn ($query) => $query->where('attribute_id', $record->id))
                        ->count();

                    if ($productCount === 0 && $variantCount === 0) {
                        return 'This will permanently delete this attribute and all its values.';
                    }

                    return "This attribute is used by {$productCount} product(s) and assigned on {$variantCount} variant(s). Deleting it removes it from all of them immediately and permanently.";
                }),
        ];
    }
}
