<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Catalog\AdjustVariantPrice;
use App\Actions\Catalog\AttachProductImage;
use App\Actions\Catalog\ConvertImageToWebp;
use App\Actions\Catalog\DeleteProductVariant;
use App\Actions\Catalog\GenerateProductVariants;
use App\Actions\Inventory\AdjustStockWithReservationCheck;
use App\Actions\Inventory\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Enums\VariantStatus;
use App\Filament\Support\MoneyInput;
use App\Models\AttributeTerm;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('sku')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                MoneyInput::make('price')
                    ->label('Price')
                    ->required()
                    ->minValue(0),

                TextInput::make('stock')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->default(0)
                    // `stock` is a cached total derived from stock_movements
                    // (technical-design-ecommerce.md's "never update stock
                    // directly" rule) — editable only at creation, where
                    // there's no ledger yet to route an initial count
                    // through. Once a variant exists, stock only ever
                    // changes via adjustStockAction()/bulkAdjustStockAction(),
                    // both of which go through AdjustStockWithReservationCheck
                    // so every change is audited and reservation-safe.
                    ->hidden(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(fn (string $operation): bool => $operation !== 'edit'),

                Placeholder::make('current_stock')
                    ->label('Stock')
                    ->content(fn (?ProductVariant $record): string => $record instanceof ProductVariant ? (string) $record->stock : '—')
                    ->helperText('Use "Adjust stock" from the variant\'s actions menu to change this — it keeps an audit trail and checks against active reservations.')
                    ->visible(fn (string $operation): bool => $operation === 'edit'),

                TextInput::make('low_stock_threshold')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Leave blank to use the store-wide default.'),

                Select::make('status')
                    ->options(VariantStatus::class)
                    ->required()
                    ->default(VariantStatus::Active),

                Select::make('attributeTerms')
                    ->label('Attribute values')
                    ->relationship(
                        'attributeTerms',
                        'value',
                        modifyQueryUsing: function (Builder $query): Builder {
                            /** @var Product $product */
                            $product = $this->getOwnerRecord();

                            // `getOptionLabelFromRecordUsing()` below reads
                            // $term->attribute per option — without this,
                            // that's a lazy load per rendered term.
                            return $query->with('attribute')->whereIn('attribute_id', $product->attributes()->pluck('attributes.id'));
                        },
                    )
                    ->getOptionLabelFromRecordUsing(fn (AttributeTerm $term): string => "{$term->attribute->name}: {$term->value}")
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->rule(fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        $attributeIds = AttributeTerm::query()->whereIn('id', $value ?? [])->pluck('attribute_id');

                        if ($attributeIds->count() !== $attributeIds->unique()->count()) {
                            $fail('Only one value per attribute can be selected (e.g. pick one Color, not two).');
                        }
                    })
                    ->helperText('Pick from the values enabled on this product\'s Attributes — no retyping needed.')
                    ->columnSpanFull(),

                Repeater::make('attributeValues')
                    ->label('Custom attributes')
                    ->relationship()
                    ->schema([
                        TextInput::make('attribute_name')
                            ->label('Name')
                            ->placeholder('e.g. Size, Color')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('value')
                            ->placeholder('e.g. Large, Red')
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel('Add attribute')
                    ->addAction(fn (Action $action) => $action->color('primary'))
                    ->helperText('Free-form — a product can mix any attributes it needs (e.g. a shirt with both Size and Color).')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            // The "Attributes" column below reads attributeTerms.attribute
            // and attributeValues per row — without eager loading here,
            // that's an N+1 (or, with lazy loading disabled outside
            // production, an outright violation).
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['attributeTerms.attribute', 'attributeValues']))
            ->columns([
                TextColumn::make('sku')
                    ->searchable(),
                TextColumn::make('price_formatted')
                    ->label('Price'),
                TextColumn::make('stock')
                    ->sortable()
                    ->color(fn (ProductVariant $record): ?string => $record->isLowStock() ? 'warning' : null)
                    ->weight(fn (ProductVariant $record): ?string => $record->isLowStock() ? 'bold' : null),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('attributeTerms')
                    ->label('Attributes')
                    ->state(function (ProductVariant $record): string {
                        $terms = $record->attributeTerms
                            ->map(fn (AttributeTerm $term): string => "{$term->attribute->name}: {$term->value}");

                        $custom = $record->attributeValues
                            ->map(fn (AttributeValue $attributeValue): string => "{$attributeValue->attribute_name}: {$attributeValue->value}");

                        return $terms->concat($custom)->implode(', ');
                    })
                    ->placeholder('—'),
                TextColumn::make('images_count')
                    ->label('Images')
                    ->counts('images')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                $this->createAction(),
                $this->generateVariantsAction(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::adjustStockAction(),
                    self::addImageAction(),
                    self::deleteAction(),
                ])
                    ->label('More actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size(Size::Small)
                    ->color('primary')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::deleteBulkAction(),
                    self::bulkAdjustStockAction(),
                    self::bulkAdjustPriceAction(),
                ]),
            ])
            ->emptyStateHeading('No variants yet')
            ->emptyStateDescription('A product can\'t be sold without at least one variant.')
            ->emptyStateIcon(Heroicon::OutlinedCube)
            ->emptyStateActions([
                $this->createAction(),
            ]);
    }

    /**
     * Created with `stock` at 0, not the submitted value — the initial
     * count is applied through RecordStockMovement afterward so it's
     * backed by a real ledger entry, same as every other write to a
     * variant's stock. Without this, `stock` and `stock_movements` drift
     * apart the instant a variant is created with nonzero stock, which is
     * exactly what `StockCacheMatchesMovements` (System Health, Tier 3)
     * exists to catch.
     */
    private function createAction(): CreateAction
    {
        return CreateAction::make()
            ->using(function (array $data): ProductVariant {
                $initialStock = (int) ($data['stock'] ?? 0);
                $data['stock'] = 0;

                /** @var Product $product */
                $product = $this->getOwnerRecord();

                /** @var ProductVariant $variant */
                $variant = $product->variants()->create($data);

                if ($initialStock > 0) {
                    RecordStockMovement::run($variant, StockMovementType::Restock, $initialStock, Auth::user(), 'Initial stock at creation');
                }

                return $variant;
            });
    }

    /**
     * Bulk-creates every combination across a set of the product's enabled
     * global attributes (e.g. Size × Color) instead of adding each variant
     * by hand — a shirt in 3 sizes and 3 colors becomes one form submission
     * instead of 9. Only offers attributes/terms from the global catalog
     * (Product::attributes()) — there's no bulk equivalent for the
     * free-typed "Custom attributes" path, same as WooCommerce's own
     * variation generator.
     */
    private function generateVariantsAction(): Action
    {
        return Action::make('generateVariants')
            ->label('Generate variants')
            ->icon(Heroicon::OutlinedSquares2x2)
            ->modalWidth(Width::ExtraLarge)
            ->schema([
                Repeater::make('attributeGroups')
                    ->label('Attributes')
                    ->schema([
                        Select::make('attribute_id')
                            ->label('Attribute')
                            ->options(function (): array {
                                /** @var Product $product */
                                $product = $this->getOwnerRecord();

                                return $product->attributes()->pluck('attributes.name', 'attributes.id')->all();
                            })
                            ->live()
                            ->distinct()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                            ->required(),

                        Select::make('term_ids')
                            ->label('Values')
                            ->multiple()
                            ->required()
                            ->options(function (callable $get): array {
                                $attributeId = $get('attribute_id');

                                if (blank($attributeId)) {
                                    return [];
                                }

                                return AttributeTerm::query()->where('attribute_id', $attributeId)->pluck('value', 'id')->all();
                            }),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->required()
                    ->addActionLabel('Add attribute')
                    ->addAction(fn (Action $action) => $action->color('primary'))
                    ->helperText('e.g. Size: L, M, XL and Color: White, Blue, Black generates every combination (9 variants). Enable attributes on the product first if none are listed.')
                    ->columnSpanFull(),

                TextInput::make('sku_prefix')
                    ->label('SKU prefix')
                    ->required()
                    ->maxLength(255),

                Grid::make(2)
                    ->schema([
                        MoneyInput::make('price')
                            ->label('Price')
                            ->required()
                            ->minValue(0),

                        TextInput::make('stock')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(0),
                    ]),
            ])
            ->fillForm(function (): array {
                /** @var Product $product */
                $product = $this->getOwnerRecord();

                return ['sku_prefix' => str($product->slug)->slug()->upper()->toString()];
            })
            ->action(function (array $data): void {
                /** @var Product $product */
                $product = $this->getOwnerRecord();

                /** @var array<int, array{attribute_id: int, term_ids: array<int, int>}> $attributeGroupInputs */
                $attributeGroupInputs = $data['attributeGroups'];

                $attributeIds = collect($attributeGroupInputs)->pluck('attribute_id');

                if ($attributeIds->count() !== $attributeIds->unique()->count()) {
                    Notification::make()
                        ->title('Each attribute can only be added once')
                        ->body('Combine its values into a single row instead of adding the same attribute twice.')
                        ->danger()
                        ->send();

                    return;
                }

                $termGroups = collect($attributeGroupInputs)
                    ->map(fn (array $group): array => array_map('intval', $group['term_ids']))
                    ->values()
                    ->all();

                $created = GenerateProductVariants::run(
                    $product,
                    $termGroups,
                    (int) $data['price'],
                    (int) $data['stock'],
                    $data['sku_prefix'],
                    Auth::user(),
                );

                $combinationCount = collect($termGroups)->map(fn (array $ids): int => count($ids))->reduce(fn (int $carry, int $count): int => $carry * $count, 1);
                $skipped = $combinationCount - $created->count();

                Notification::make()
                    ->title("{$created->count()} variant(s) created".($skipped > 0 ? ", {$skipped} skipped (already existed)" : ''))
                    ->success()
                    ->send();
            });
    }

    /**
     * Routes through DeleteProductVariant (SKU mutation for reuse safety)
     * instead of Filament's plain delete, and surfaces an extra notification
     * when this was the product's last variant and it got auto-downgraded
     * to Draft as a result.
     */
    private static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->action(function (ProductVariant $record): void {
                $wasDowngraded = DeleteProductVariant::run($record);

                Notification::make()->title('Variant deleted')->success()->send();

                if ($wasDowngraded) {
                    Notification::make()
                        ->title('Product set to Draft')
                        ->body('This was the last variant, so the product has no variants left to sell.')
                        ->warning()
                        ->send();
                }
            });
    }

    private static function deleteBulkAction(): BulkAction
    {
        return DeleteBulkAction::make()
            ->action(function (Collection $records): void {
                $downgradedAny = false;

                foreach ($records as $record) {
                    if ($record instanceof ProductVariant) {
                        $downgradedAny = DeleteProductVariant::run($record) || $downgradedAny;
                    }
                }

                Notification::make()->title('Variants deleted')->success()->send();

                if ($downgradedAny) {
                    Notification::make()
                        ->title('Product set to Draft')
                        ->body('This left the product with no variants left to sell.')
                        ->warning()
                        ->send();
                }
            });
    }

    /**
     * Attaches one or more images directly to the variant its row belongs
     * to — no "which variant?" selector needed, since the row itself is
     * the context. Each uploaded file becomes its own ProductImage row
     * (visible individually on the product's Images tab), auto-ordered
     * after whatever images the variant already has. No sort_order/
     * is_primary inputs here — reasoning about a "starting position"
     * across a batch of files isn't worth the friction on a quick-add
     * action; both are still adjustable afterward via the Images tab,
     * where each row is edited individually and unambiguous.
     */
    private static function addImageAction(): Action
    {
        return Action::make('addImage')
            ->label('Add image')
            ->icon(Heroicon::OutlinedPhoto)
            ->authorize(fn (): bool => Auth::user()?->can('create', ProductImage::class) ?? false)
            ->schema([
                FileUpload::make('images')
                    ->label('Images')
                    ->image()
                    ->multiple()
                    ->maxFiles(10)
                    ->reorderable()
                    ->maxSize(config('media.max_upload_size_kb'))
                    ->disk('public')
                    ->directory('product-images')
                    ->saveUploadedFileUsing(ConvertImageToWebp::forFileUpload())
                    ->required(),
            ])
            ->action(function (ProductVariant $record, array $data): void {
                /** @var Product $product */
                $product = $record->product;
                $paths = array_values($data['images']);
                $nextSortOrder = $record->images()->max('sort_order');
                $nextSortOrder = $nextSortOrder === null ? 0 : $nextSortOrder + 1;

                foreach ($paths as $index => $path) {
                    AttachProductImage::run(
                        $product,
                        $path,
                        $record,
                        $nextSortOrder + $index,
                        isPrimary: false,
                    );
                }

                Notification::make()
                    ->title(count($paths) === 1 ? 'Image added' : count($paths).' images added')
                    ->success()
                    ->send();
            });
    }

    /**
     * The single-row counterpart to bulkAdjustStockAction() — the only way
     * to change an existing variant's stock (the Edit form's `stock` field
     * is create-only; see form()). Routes through
     * AdjustStockWithReservationCheck so every change is ledgered and
     * checked against active reservations, same as the bulk version.
     */
    private static function adjustStockAction(): Action
    {
        return Action::make('adjustStock')
            ->label('Adjust stock')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->schema([
                TextInput::make('delta')
                    ->label('Change (+/-)')
                    ->numeric()
                    ->required()
                    ->helperText('Positive to add stock, negative to remove it.'),
                TextInput::make('note')
                    ->maxLength(255),
            ])
            ->action(function (ProductVariant $record, array $data): void {
                AdjustStockWithReservationCheck::run($record, (int) $data['delta'], Auth::user(), $data['note'] ?? null);

                Notification::make()->title('Stock adjusted')->success()->send();
            });
    }

    private static function bulkAdjustStockAction(): BulkAction
    {
        return BulkAction::make('bulkAdjustStock')
            ->label('Adjust stock')
            ->schema([
                TextInput::make('delta')
                    ->label('Change (+/-)')
                    ->numeric()
                    ->required()
                    ->helperText('Positive to add stock, negative to remove it.'),
                TextInput::make('note')
                    ->maxLength(255),
            ])
            ->action(function (Collection $records, array $data): void {
                foreach ($records as $record) {
                    if ($record instanceof ProductVariant) {
                        AdjustStockWithReservationCheck::run($record, (int) $data['delta'], Auth::user(), $data['note'] ?? null);
                    }
                }

                Notification::make()->title('Stock adjusted')->success()->send();
            });
    }

    private static function bulkAdjustPriceAction(): BulkAction
    {
        return BulkAction::make('bulkAdjustPrice')
            ->label('Adjust price')
            ->schema([
                TextInput::make('percentage')
                    ->label('Change (%)')
                    ->numeric()
                    ->required()
                    ->helperText('e.g. 10 to increase by 10%, -10 to decrease by 10%.'),
            ])
            ->action(function (Collection $records, array $data): void {
                $percentage = (float) $data['percentage'];

                DB::transaction(function () use ($records, $percentage): void {
                    foreach ($records as $record) {
                        if ($record instanceof ProductVariant) {
                            AdjustVariantPrice::run($record, $percentage);
                        }
                    }
                });

                Notification::make()->title('Prices updated')->success()->send();
            });
    }
}
