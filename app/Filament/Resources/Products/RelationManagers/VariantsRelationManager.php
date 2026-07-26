<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Catalog\AttachProductImage;
use App\Actions\Inventory\AdjustStockWithReservationCheck;
use App\Enums\VariantStatus;
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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
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

                Repeater::make('attributeValues')
                    ->label('Attributes')
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
                    ->addActionLabel('Add attribute')
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
                TextColumn::make('attributeValues')
                    ->label('Attributes')
                    ->state(fn (ProductVariant $record): string => $record->attributeValues
                        ->map(fn (AttributeValue $attributeValue): string => "{$attributeValue->attribute_name}: {$attributeValue->value}")
                        ->implode(', '))
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
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::addImageAction(),
                    DeleteAction::make(),
                ])
                    ->label('More actions')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size(Size::Small)
                    ->color('primary')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
