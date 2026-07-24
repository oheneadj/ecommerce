<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Placeholder::make('order_number')
                    ->content(fn ($record) => $record?->order_number),

                Placeholder::make('customer')
                    ->content(fn ($record) => optional($record->user)->name ?? $record->guest_email ?? 'Guest'),

                Placeholder::make('grand_total')
                    ->label('Total')
                    ->content(fn ($record) => $record?->grand_total_formatted),

                Select::make('status')
                    ->options(OrderStatus::class)
                    ->required(),

                Textarea::make('status_change_note')
                    ->label('Note')
                    ->helperText('Recorded in this order\'s status history alongside the change.')
                    ->columnSpanFull(),
            ]);
    }
}
