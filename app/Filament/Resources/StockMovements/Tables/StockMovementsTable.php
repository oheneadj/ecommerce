<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Tables;

use App\Enums\StockMovementType;
use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('productVariant.sku')
                    ->label('Variant')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('quantity')
                    ->sortable(),
                TextColumn::make('note')
                    ->limit(40)
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('System'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(StockMovementType::class),
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ])
            ->emptyStateHeading('No stock movements yet')
            ->emptyStateDescription('Record a manual restock or adjustment to get started.')
            ->emptyStateIcon(Heroicon::OutlinedArrowsRightLeft)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
