<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Tables;

use App\Actions\Order\AssignShipment;
use App\Actions\Order\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ShippingMethod;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use pxlrbt\FilamentExcel\Actions\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

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
                ActionGroup::make([
                    self::updateStatusAction()
                        ->button(),
                    self::assignShipmentAction()
                        ->button(),
                    self::downloadInvoiceAction()
                        ->button(),
                ])
                    ->label('Actions')
                    ->buttonGroup(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkUpdateStatusAction(),
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withColumns([
                                    'order_number',
                                    'status',
                                    'subtotal',
                                    'discount_total',
                                    'shipping_total',
                                    'tax_total',
                                    'grand_total',
                                    'created_at',
                                ]),
                        ]),
                ]),
            ])
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Orders will appear here once customers start checking out.')
            ->emptyStateIcon(Heroicon::OutlinedShoppingBag);
    }

    private static function bulkUpdateStatusAction(): BulkAction
    {
        return BulkAction::make('bulkUpdateStatus')
            ->label('Update status')
            ->schema([
                Select::make('status')
                    ->options(OrderStatus::class)
                    ->required(),
            ])
            ->action(function (Collection $records, array $data): void {
                $status = $data['status'] instanceof OrderStatus ? $data['status'] : OrderStatus::from($data['status']);

                foreach ($records as $record) {
                    if ($record instanceof Order) {
                        UpdateOrderStatus::run($record, $status, Auth::user());
                    }
                }

                Notification::make()->title('Orders updated')->success()->send();
            });
    }

    /**
     * A modal (not a full edit-page navigation) so updating an order's
     * status stays a quick in-place action from the table.
     */
    private static function updateStatusAction(): Action
    {
        return Action::make('updateStatus')
            ->label('Update status')
            ->modalWidth(Width::Small)
            ->schema([
                Select::make('status')
                    ->options(OrderStatus::class)
                    ->required(),
                Textarea::make('status_change_note')
                    ->label('Note')
                    ->placeholder('Optional note for the order history.')
                    ->helperText('This note is recorded alongside the status change.')
                    ->columnSpanFull(),
            ])
            ->fillForm(fn (Order $record): array => ['status' => $record->status])
            ->action(function (Order $record, array $data): void {
                $status = $data['status'] instanceof OrderStatus ? $data['status'] : OrderStatus::from($data['status']);

                UpdateOrderStatus::run($record, $status, Auth::user(), $data['status_change_note'] ?? null);

                Notification::make()->title('Order status updated')->success()->send();
            });
    }

    private static function assignShipmentAction(): Action
    {
        return Action::make('assignShipment')
            ->label('Assign shipment')
            ->modalWidth(Width::Small)
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
