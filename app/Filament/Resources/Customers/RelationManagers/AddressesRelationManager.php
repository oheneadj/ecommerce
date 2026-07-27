<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Read-only — every address this customer has saved (not just ones that
 * happen to be attached to a past order).
 */
class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('recipient_name')
            ->columns([
                TextColumn::make('label')
                    ->placeholder('—'),
                TextColumn::make('recipient_name')
                    ->label('Recipient'),
                TextColumn::make('phone'),
                TextColumn::make('line1')
                    ->label('Address')
                    ->formatStateUsing(fn ($record): string => collect([
                        $record->line1,
                        $record->line2,
                        $record->city,
                        $record->region,
                    ])->filter()->implode(', ')),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
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
            ->emptyStateHeading('No addresses yet')
            ->emptyStateIcon(Heroicon::OutlinedMapPin);
    }
}
