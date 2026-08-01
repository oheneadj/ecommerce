<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Tables;

use App\Actions\Order\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderRecordActions;
use App\Models\Order;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
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
                ViewAction::make()
                    ->button(),
                ActionGroup::make([
                    OrderRecordActions::updateStatus()
                        ->button(),
                    OrderRecordActions::assignShipment()
                        ->button(),
                    OrderRecordActions::downloadInvoice()
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
            ->authorize(fn (): bool => Auth::user()?->can('viewAny', Order::class) ?? false)
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
}
