<?php

/**
 * Manages a product's images from its edit page — both general product
 * images and images scoped to one specific variant.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Catalog\ConvertImageToWebp;
use App\Models\Product;
use App\Models\ProductImage;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * An image can be scoped three ways, in priority order at storefront
 * render time (see `ProductVariant::galleryImages()`):
 *
 *   1. `product_variant_id` set — this one exact variant only (e.g. a
 *      specific size photographed on its own).
 *   2. `attribute_term_id` set — every variant carrying that attribute
 *      value (e.g. "Color: Green"), so one upload covers every size of
 *      that color instead of needing one per exact variant.
 *   3. Both null — a general product image, shown when nothing more
 *      specific matches.
 *
 * The two scope columns are mutually exclusive; the form only shows one
 * at a time based on the (non-persisted) `scope_type` field and nulls
 * out the other before saving.
 */
class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label('Image')
                    ->image()
                    ->maxSize(config('media.max_upload_size_kb'))
                    ->disk('public')
                    ->directory('product-images')
                    ->saveUploadedFileUsing(ConvertImageToWebp::forFileUpload())
                    ->required(),

                Select::make('scope_type')
                    ->label('Scope')
                    ->options([
                        'general' => 'General (whole product)',
                        'attribute_term' => 'Attribute value (e.g. a color, shared across sizes)',
                        'variant' => 'Specific variant',
                    ])
                    ->default('general')
                    ->live()
                    ->afterStateHydrated(function (Select $component, ?ProductImage $record): void {
                        $component->state(match (true) {
                            $record?->product_variant_id !== null => 'variant',
                            $record?->attribute_term_id !== null => 'attribute_term',
                            default => 'general',
                        });
                    })
                    ->helperText('General shows on the product until something more specific matches; a variant image always wins over an attribute-value image.'),

                Select::make('attribute_term_id')
                    ->label('Attribute value')
                    ->visible(fn (Get $get): bool => $get('scope_type') === 'attribute_term')
                    ->options(function (): array {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();

                        // Not `flatMap()` — it collapses like `array_merge`,
                        // which re-indexes integer keys and would lose the
                        // actual term ids that `Select` needs to validate
                        // against. `+` (union) preserves them.
                        $options = [];

                        foreach ($product->attributes()->with('terms')->get() as $attribute) {
                            $options += $attribute->terms->mapWithKeys(fn ($term) => [
                                $term->id => "{$attribute->name}: {$term->value}",
                            ])->all();
                        }

                        return $options;
                    })
                    ->searchable()
                    ->required(fn (Get $get): bool => $get('scope_type') === 'attribute_term'),

                Select::make('product_variant_id')
                    ->label('Variant')
                    ->visible(fn (Get $get): bool => $get('scope_type') === 'variant')
                    ->options(function (): array {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();

                        return $product->variants()->pluck('sku', 'id')->all();
                    })
                    ->required(fn (Get $get): bool => $get('scope_type') === 'variant'),

                TextInput::make('sort_order')
                    ->label('Display order')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Toggle::make('is_primary')
                    ->label('Primary image'),
            ]);
    }

    /**
     * The `scope_type` field is a UI-only convenience (not a real column) —
     * clear whichever of the two real scope columns doesn't match it, so
     * switching scope in the form can't leave a stale value behind on the
     * one that's now hidden.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function clearInactiveScopeColumn(array $data): array
    {
        if (($data['scope_type'] ?? 'general') !== 'variant') {
            $data['product_variant_id'] = null;
        }

        if (($data['scope_type'] ?? 'general') !== 'attribute_term') {
            $data['attribute_term_id'] = null;
        }

        unset($data['scope_type']);

        return $data;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            ->columns([
                ImageColumn::make('path')
                    ->label('Image')
                    ->disk('public')
                    ->imageHeight(60),
                TextColumn::make('scope')
                    ->label('Scope')
                    ->badge()
                    ->state(function (ProductImage $record): string {
                        if ($record->product_variant_id !== null) {
                            return $record->productVariant->sku;
                        }

                        if ($record->attribute_term_id !== null) {
                            return "{$record->attributeTerm->attribute->name}: {$record->attributeTerm->value}";
                        }

                        return 'General';
                    })
                    ->color(fn (ProductImage $record): string => $record->product_variant_id === null && $record->attribute_term_id === null ? 'gray' : 'info'),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(fn (array $data): array => $this->clearInactiveScopeColumn($data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->button()
                    ->mutateDataUsing(fn (array $data): array => $this->clearInactiveScopeColumn($data)),
                $this->deleteAction(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->before(function (Collection $records): void {
                        Storage::disk('public')->delete($records->pluck('path')->all());
                    }),
            ])
            ->emptyStateHeading('No images yet')
            ->emptyStateDescription('Upload a general product photo, or scope one to a specific variant.')
            ->emptyStateIcon(Heroicon::OutlinedPhoto)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    /**
     * Deletes the stored file alongside the database row, so removing an
     * image never leaves an orphaned file behind in storage.
     */
    private function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->button()
            ->before(function (ProductImage $record): void {
                Storage::disk('public')->delete($record->path);
            });
    }
}
