<?php

/**
 * Read-only detail view for an order — its own status/financials, the
 * customer it belongs to (linked to the Customers resource), and shipping
 * destination. Order items live in the Items relation manager tab.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Order;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('Order number'),
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('created_at')
                                    ->label('Placed')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Customer')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Customer')
                                    ->placeholder(fn (Order $record) => $record->guest_email ?? 'Guest')
                                    ->url(fn (Order $record): ?string => $record->user_id !== null
                                        ? CustomerResource::getUrl('view', ['record' => $record->user_id])
                                        : null),
                                TextEntry::make('contact')
                                    ->label('Contact')
                                    ->state(function (Order $record): string {
                                        if ($record->user_id === null) {
                                            return $record->guest_phone ?? $record->guest_email ?? '—';
                                        }

                                        return $record->user->phone ?? $record->user->email ?? '—';
                                    }),
                            ]),
                    ]),

                Section::make('Shipping')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('address.recipient_name')
                                    ->label('Recipient'),
                                TextEntry::make('address.phone')
                                    ->label('Phone'),
                                TextEntry::make('address.line1')
                                    ->label('Address')
                                    ->formatStateUsing(fn (Order $record): string => collect([
                                        $record->address->line1,
                                        $record->address->line2,
                                        $record->address->city,
                                        $record->address->region,
                                    ])->filter()->implode(', '))
                                    ->columnSpanFull(),
                                TextEntry::make('shipment.status')
                                    ->label('Shipment status')
                                    ->badge()
                                    ->placeholder('Not yet assigned'),
                                TextEntry::make('shipment.tracking_number')
                                    ->label('Tracking number')
                                    ->placeholder('—'),
                            ]),
                    ]),

                Section::make('Financials')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('subtotal_formatted')
                                    ->label('Subtotal'),
                                TextEntry::make('discount_total_formatted')
                                    ->label('Discount'),
                                TextEntry::make('shipping_total_formatted')
                                    ->label('Shipping'),
                                TextEntry::make('tax_total_formatted')
                                    ->label('Tax'),
                                TextEntry::make('grand_total_formatted')
                                    ->label('Grand total')
                                    ->weight('bold'),
                            ]),
                    ]),
            ]);
    }
}
