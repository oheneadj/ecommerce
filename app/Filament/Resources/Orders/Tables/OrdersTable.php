<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Tables;

use App\Actions\Order\AssignShipment;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ShippingMethod;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->placeholder(fn ($record) => $record->guest_email ?? 'Guest')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('shipment.status')
                    ->label('Shipment')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('grand_total_formatted')
                    ->label('Total'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(OrderStatus::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Update status'),
                self::assignShipmentAction(),
                self::downloadInvoiceAction(),
            ])
            ->toolbarActions([
                //
            ]);
    }

    private static function assignShipmentAction(): Action
    {
        return Action::make('assignShipment')
            ->label('Assign shipment')
            ->schema([
                Select::make('shipping_method_id')
                    ->label('Shipping method')
                    ->options(fn () => ShippingMethod::query()->where('active', true)->pluck('name', 'id'))
                    ->required(),
                TextInput::make('tracking_number')
                    ->maxLength(255),
            ])
            ->fillForm(fn (Order $record) => [
                'shipping_method_id' => $record->shipment?->shipping_method_id,
                'tracking_number' => $record->shipment?->tracking_number,
            ])
            ->action(function (Order $record, array $data): void {
                AssignShipment::run(
                    $record,
                    ShippingMethod::query()->findOrFail($data['shipping_method_id']),
                    $data['tracking_number'] ?? null,
                );

                Notification::make()->title('Shipment assigned')->success()->send();
            });
    }

    private static function downloadInvoiceAction(): Action
    {
        return Action::make('downloadInvoice')
            ->label('Download invoice')
            ->visible(fn (Order $record) => $record->invoice_path !== null)
            ->action(fn (Order $record) => Storage::disk('local')->download($record->invoice_path, "{$record->order_number}.pdf"));
    }
}
