<?php

namespace App\Filament\Resources\StockReservations;

use App\Filament\Resources\StockReservations\Pages\ListStockReservations;
use App\Filament\Resources\StockReservations\Tables\StockReservationsTable;
use App\Models\StockReservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
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

    public static function table(Table $table): Table
    {
        return StockReservationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockReservations::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
