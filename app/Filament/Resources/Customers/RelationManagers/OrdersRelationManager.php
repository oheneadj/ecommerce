<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only — this customer's order history. Managing an order (status,
 * shipment, invoice) happens on the Order's own page, linked from here.
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order_number')
            ->columns([
                TextColumn::make('order_number')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('grand_total_formatted')
                    ->label('Total'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View order')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn ($record): string => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([
                //
            ])
            ->emptyStateHeading('No orders yet')
            ->emptyStateIcon(Heroicon::OutlinedShoppingBag);
    }
}
