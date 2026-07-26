<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockReservations\Tables;

use App\Enums\StockReservationStatus;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockReservationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('productVariant.sku')
                    ->label('Variant')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('order_id')
                    ->label('Order')
                    ->placeholder('—'),
                TextColumn::make('quantity'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(StockReservationStatus::class),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ])
            ->emptyStateHeading('No stock reservations')
            ->emptyStateDescription('Reservations are created automatically when a customer checks out.')
            ->emptyStateIcon(Heroicon::OutlinedClock);
    }
}
