<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only — refunds are only ever created via the "Issue refund" action
 * on the Payments table (which calls ProcessRefund), never by hand here.
 */
class RefundsRelationManager extends RelationManager
{
    protected static string $relationship = 'refunds';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('amount_formatted')
                    ->label('Amount'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('reason')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->dateTime(),
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
            ->emptyStateHeading('No refunds')
            ->emptyStateDescription('Refunds issued against this payment will appear here.')
            ->emptyStateIcon(Heroicon::OutlinedBanknotes);
    }
}
