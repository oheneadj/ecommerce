<?php

/**
 * Filament resource for viewing stock reservations — read-only, no create/
 * edit; reservations are managed entirely by the inventory Actions.
 */

declare(strict_types=1);

namespace App\Filament\Resources\StockReservations;

use App\Filament\Resources\StockReservations\Pages\ListStockReservations;
use App\Filament\Resources\StockReservations\Tables\StockReservationsTable;
use App\Models\StockReservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Read-only: reservations are created only by ReserveStockForOrder and
 * transitioned only by ReleaseExpiredReservations / AdjustStockWithReservationCheck /
 * payment-webhook handling — never edited by hand.
 */
class StockReservationResource extends Resource
{
    protected static ?string $model = StockReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    /**
     * Builds the stock reservations list table.
     */
    public static function table(Table $table): Table
    {
        return StockReservationsTable::configure($table);
    }

    /**
     * Eager loads the related product variant to avoid N+1s on the list table.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['productVariant']);
    }

    /**
     * No relation managers for this resource.
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Registers the resource's index page.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListStockReservations::route('/'),
        ];
    }

    /**
     * Disables manual reservation creation — reservations are only created
     * by ReserveStockForOrder.
     */
    public static function canCreate(): bool
    {
        return false;
    }
}
