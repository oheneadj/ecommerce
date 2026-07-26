<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only — order items are permanently snapshotted at checkout and
 * never edited afterward (BRD Principle 8).
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('item_snapshot.product_name')
                    ->label('Product'),
                TextColumn::make('item_snapshot.sku')
                    ->label('SKU'),
                TextColumn::make('unit_price_formatted')
                    ->label('Unit price'),
                TextColumn::make('quantity'),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ])
            ->emptyStateHeading('No items')
            ->emptyStateIcon(Heroicon::OutlinedShoppingBag);
    }
}
