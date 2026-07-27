<?php

/**
 * Read-only detail view for a customer account.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Schemas;

use App\Models\User;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CustomerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Customer')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('name')
                                    ->placeholder('—'),
                                TextEntry::make('phone')
                                    ->placeholder('—'),
                                TextEntry::make('email')
                                    ->placeholder('—'),
                                IconEntry::make('google_linked')
                                    ->label('Google account linked')
                                    ->boolean()
                                    ->state(fn (User $record): bool => $record->google_id !== null),
                                TextEntry::make('created_at')
                                    ->label('Joined')
                                    ->dateTime(),
                            ]),
                    ]),
            ]);
    }
}
