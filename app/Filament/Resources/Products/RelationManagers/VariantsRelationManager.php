<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Actions\Inventory\AdjustStockWithReservationCheck;
use App\Enums\VariantStatus;
use App\Models\ProductVariant;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
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
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
