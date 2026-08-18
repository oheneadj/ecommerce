<?php

/**
 * Manages a product's images from its edit page — both general product
 * images and images scoped to one specific variant.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Catalog\ConvertImageToWebp;
use App\Exceptions\ProductImageLimitExceededException;
use App\Models\AttributeTerm;
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
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
            // Two separate fields, not one FileUpload toggling `multiple()`
            // by operation — that turned out fragile (Filament's own
            // hydration of an existing record's single `path` column got
            // confused switching modes on the same component) and the
            // schema-level `components(Closure)` doesn't receive an
            // `operation` argument (only individual field-level closures
            // do), so the fields can't be swapped in/out that way either.
            // Distinct names (`path` vs `images`) avoid a state-binding
            // collision between the two; each is hidden AND not required
            // on the operation it doesn't apply to, so only one is ever
            // active/validated at a time.
            ->components([
                $this->singleImageUploadField(),
                $this->multiImageUploadField(),

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

                        // Only terms this product's own variants actually
                        // carry — not every term the attribute has ever
                        // had globally (e.g. "Color" having 20 values
                        // catalog-wide when this product only has Red and
                        // Blue variants). An attribute-value-scoped image
                        // only ever shows on a variant carrying that exact
                        // term, so offering an unused one here is a choice
                        // that could never actually apply to anything.
                        return AttributeTerm::query()
                            ->whereHas('productVariants', fn (Builder $query): Builder => $query->where('product_variants.product_id', $product->id))
                            ->with('attribute')
                            ->get()
                            ->mapWithKeys(fn (AttributeTerm $term): array => [$term->id => "{$term->attribute->name}: {$term->value}"])
                            ->all();
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

                // Hidden on create — with possibly-multiple files in one
                // submission, asking the admin to reason about a single
                // "starting position"/"is this the primary one" up front
                // isn't worth the friction. Order is auto-assigned and no
                // upload auto-becomes primary; both stay adjustable per
                // row afterward via Edit, where they're unambiguous.
                TextInput::make('sort_order')
                    ->label('Display order')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->hiddenOn('create'),

                Toggle::make('is_primary')
                    ->label('Primary image')
                    ->hiddenOn('create'),
            ]);
    }

    private function singleImageUploadField(): FileUpload
    {
        return FileUpload::make('path')
            ->label('Image')
            ->image()
            ->maxSize(config('media.max_upload_size_kb'))
            ->disk('public')
            ->directory('product-images')
            ->saveUploadedFileUsing(ConvertImageToWebp::forFileUpload())
            ->hiddenOn('create')
            ->required(fn (string $operation): bool => $operation !== 'create')
            // Default record-hydration wasn't picking up the existing
            // scalar `path` value on edit (came through empty) once a
            // second FileUpload component was added to this schema —
            // set it explicitly, same pattern already used for
            // `scope_type` above.
            ->afterStateHydrated(function (FileUpload $component, ?ProductImage $record): void {
                if ($record !== null) {
                    $component->state($record->path);
                }
            });
    }

    private function multiImageUploadField(): FileUpload
    {
        return FileUpload::make('images')
            ->label('Image(s)')
            ->image()
            ->multiple()
            ->maxFiles(fn (): int => config('media.product_max_images'))
            ->maxSize(config('media.max_upload_size_kb'))
            ->disk('public')
            ->directory('product-images')
            ->saveUploadedFileUsing(ConvertImageToWebp::forFileUpload())
            ->visibleOn('create')
            ->required(fn (string $operation): bool => $operation === 'create');
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

    /**
     * Overrides Filament's default `CreateAction` behavior (which only
     * ever creates one record) — `path` arrives as an array of already-
     * converted-to-webp paths when multiple files were uploaded, so this
     * creates one `ProductImage` row per file, all sharing the same scope
     * (`clearInactiveScopeColumn` has already run via `mutateDataUsing`),
     * auto-assigned `sort_order` continuing on from this product's
     * existing images, never auto-primary. Returns the first created
     * record — Filament only needs *a* record back to close out its own
     * create lifecycle (notification, modal close, table refresh); which
     * one is irrelevant here since there's nothing else to do with it.
     *
     * @param  array<string, mixed>  $data
     */
    private function createImagesFromUpload(array $data): ProductImage
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        $paths = array_values((array) $data['images']);
        unset($data['images'], $data['path']);

        // maxFiles() on the field only caps a single upload — an admin
        // running "Add image" repeatedly could still exceed the product's
        // total limit, so the real enforcement is this count against every
        // image already on the product.
        $limit = (int) config('media.product_max_images');

        if ($product->images()->count() + count($paths) > $limit) {
            Notification::make()->title('Cannot add image(s)')->body((new ProductImageLimitExceededException($limit))->getMessage())->danger()->send();

            throw new Halt;
        }

        $nextSortOrder = $product->images()->max('sort_order');
        $nextSortOrder = $nextSortOrder === null ? 0 : $nextSortOrder + 1;

        $created = [];

        foreach ($paths as $index => $path) {
            $created[] = $product->images()->create([
                ...$data,
                'path' => $path,
                'sort_order' => $nextSortOrder + $index,
                'is_primary' => false,
            ]);
        }

        return $created[0];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('path')
            // The "Scope" column below reads productVariant/attributeTerm.
            // attribute per row — without eager loading here, that's an
            // N+1 (previously silent; only surfaced once 2+ rows shared
            // the same scope, since Eloquent's lazy-loading guard skips a
            // relation that only ever batch-hydrates a single row).
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['productVariant', 'attributeTerm.attribute']))
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
                    ->label('Order'),
                IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->headerActions([
                $this->createAction(),
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
                $this->createAction(),
            ]);
    }

    private function createAction(): CreateAction
    {
        return CreateAction::make()
            ->mutateDataUsing(fn (array $data): array => $this->clearInactiveScopeColumn($data))
            ->using(fn (array $data): ProductImage => $this->createImagesFromUpload($data));
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
