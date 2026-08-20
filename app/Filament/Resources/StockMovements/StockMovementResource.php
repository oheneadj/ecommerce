<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements;

use App\Filament\Resources\StockMovements\Pages\CreateStockMovement;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\Schemas\StockMovementForm;
use App\Filament\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\StockMovement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Filament resource for browsing and manually recording stock movements
 * (restocks, adjustments, returns, damage) against product variants.
 */
class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    /**
     * Configures the stock movement create form.
     */
    public static function form(Schema $schema): Schema
    {
        return StockMovementForm::configure($schema);
    }

    /**
     * Configures the stock movements list table.
     */
    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    /**
     * Eager loads the product variant and user relations for the list table.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['productVariant', 'user']);
    }

    /**
     * No relation managers on this resource.
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Registers the pages available on this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
            'create' => CreateStockMovement::route('/create'),
        ];
    }
}
