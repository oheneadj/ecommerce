<?php

/**
 * Detailed, Super-Admin-only view of a single payment — every field plus
 * the provider's raw callback metadata. The full request/response payload
 * for each API call is in the API Logs tab (ApiLogsRelationManager).
 */

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Schemas;

use App\Models\Payment;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Payment')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('order.order_number')
                                    ->label('Order'),
                                TextEntry::make('provider')
                                    ->badge(),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('amount_formatted')
                                    ->label('Amount'),
                                TextEntry::make('provider_reference')
                                    ->label('Provider reference')
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('created_at')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Provider metadata')
                    ->description('The raw callback payload stored against this payment.')
                    ->schema([
                        CodeEntry::make('metadata')
                            ->label('')
                            ->grammar('json')
                            ->state(fn (Payment $record): string => json_encode($record->metadata ?? [], JSON_PRETTY_PRINT) ?: '{}')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }
}
