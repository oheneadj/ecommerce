<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->searchable()
                    ->placeholder('—'),
                IconColumn::make('google_id')
                    ->label('Google')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => $record->google_id !== null),
                TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders'),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->button(),
            ])
            ->toolbarActions([
                //
            ])
            ->emptyStateHeading('No customers yet')
            ->emptyStateDescription('Customer accounts appear here once someone signs up.')
            ->emptyStateIcon(Heroicon::OutlinedUsers);
    }
}
