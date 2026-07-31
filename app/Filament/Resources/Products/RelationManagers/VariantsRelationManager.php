<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Catalog\AttachProductImage;
use App\Actions\Catalog\ConvertImageToWebp;
use App\Actions\Catalog\DeleteProductVariant;
use App\Actions\Catalog\GenerateProductVariants;
use App\Actions\Inventory\AdjustStockWithReservationCheck;
use App\Enums\VariantStatus;
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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

                TextInput::make('price')
                    ->label('Price (pesewas)')
                    ->numeric()
                    ->required()
                    ->minValue(0),

                TextInput::make('stock')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->default(0),

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

                            return $query->whereIn('attribute_id', $product->attributes()->pluck('attributes.id'));
                        },
                    )
                    ->getOptionLabelFromRecordUsing(fn (AttributeTerm $term): string => "{$term->attribute->name}: {$term->value}")
                    ->multiple()
                    ->preload()
                    ->searchable()
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
                CreateAction::make(),
                $this->generateVariantsAction(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
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
                CreateAction::make(),
            ]);
    }

    /**
     * Bulk-creates every combination across a set of attributes (e.g. Size ×
     * Color) instead of adding each variant by hand — a shirt in 3 sizes and
     * 3 colors becomes one form submission instead of 9.
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
                        TextInput::make('name')
                            ->label('Attribute')
                            ->placeholder('e.g. Size')
                            ->required()
                            ->maxLength(255),

                        TagsInput::make('values')
                            ->label('Values')
                            ->placeholder('Type a value and press Enter')
                            ->required(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->required()
                    ->addActionLabel('Add attribute')
                    ->addAction(fn (Action $action) => $action->color('primary'))
                    ->helperText('e.g. Size: L, M, XL and Color: White, Blue, Black generates every combination (9 variants).')
                    ->columnSpanFull(),

                TextInput::make('sku_prefix')
                    ->label('SKU prefix')
                    ->required()
                    ->maxLength(255),

                Grid::make(2)
                    ->schema([
                        TextInput::make('price')
                            ->label('Price (pesewas)')
                            ->numeric()
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

                /** @var array<int, array{name: string, values: array<int, string>}> $attributeGroupInputs */
                $attributeGroupInputs = $data['attributeGroups'];

                $attributeGroups = collect($attributeGroupInputs)
                    ->mapWithKeys(fn (array $group): array => [$group['name'] => $group['values']])
                    ->all();

                $created = GenerateProductVariants::run(
                    $product,
                    $attributeGroups,
                    (int) $data['price'],
                    (int) $data['stock'],
                    $data['sku_prefix'],
                );

                $combinationCount = collect($attributeGroups)->map(fn (array $values): int => count($values))->reduce(fn (int $carry, int $count): int => $carry * $count, 1);
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
     * Attaches an image directly to the variant its row belongs to — no
     * "which variant?" selector needed, since the row itself is the context.
     */
    private static function addImageAction(): Action
    {
        return Action::make('addImage')
            ->label('Add image')
            ->icon(Heroicon::OutlinedPhoto)
            ->authorize(fn (): bool => Auth::user()?->can('create', ProductImage::class) ?? false)
            ->schema([
                FileUpload::make('path')
                    ->label('Image')
                    ->image()
                    ->disk('public')
                    ->directory('product-images')
                    ->saveUploadedFileUsing(ConvertImageToWebp::forFileUpload())
                    ->required(),

                TextInput::make('sort_order')
                    ->label('Display order')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Toggle::make('is_primary')
                    ->label('Primary image'),
            ])
            ->action(function (ProductVariant $record, array $data): void {
                /** @var Product $product */
                $product = $record->product;

                AttachProductImage::run(
                    $product,
                    $data['path'],
                    $record,
                    (int) ($data['sort_order'] ?? 0),
                    (bool) ($data['is_primary'] ?? false),
                );

                Notification::make()->title('Image added')->success()->send();
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

                foreach ($records as $record) {
                    if ($record instanceof ProductVariant) {
                        $newPrice = (int) round($record->price * (1 + $percentage / 100));
                        $record->update(['price' => max(0, $newPrice)]);
                    }
                }

                Notification::make()->title('Prices updated')->success()->send();
            });
    }
}
