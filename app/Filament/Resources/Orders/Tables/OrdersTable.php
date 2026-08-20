<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Tables;

use App\Actions\Order\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderStatusTransitionException;
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
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Builds the orders list table — columns, filters, row actions, and bulk
 * status update/export.
 */
class OrdersTable
{
    /**
     * Configures the order list table.
     */
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
                    OrderRecordActions::regenerateInvoice()
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
                                // Plain string column names previously fataled at export
                                // time ("Call to a member function getName() on string")
                                // — withColumns() only accepts Column instances.
                                ->withColumns([
                                    Column::make('order_number'),
                                    Column::make('status'),
                                    Column::make('subtotal'),
                                    Column::make('discount_total'),
                                    Column::make('shipping_total'),
                                    Column::make('tax_total'),
                                    Column::make('grand_total'),
                                    Column::make('created_at'),
                                ]),
                        ]),
                ]),
            ])
            ->emptyStateHeading('No orders yet')
            ->emptyStateDescription('Orders will appear here once customers start checking out.')
            ->emptyStateIcon(Heroicon::OutlinedShoppingBag);
    }

    /**
     * Bulk-applies a status change via UpdateOrderStatus, skipping (and
     * reporting) any order the target status isn't a valid transition for.
     */
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

                $updated = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if (! $record instanceof Order) {
                        continue;
                    }

                    try {
                        UpdateOrderStatus::run($record, $status, Auth::user());
                        $updated++;
                    } catch (InvalidOrderStatusTransitionException) {
                        $skipped++;
                    }
                }

                $notification = Notification::make()->title("{$updated} order(s) updated");

                if ($skipped > 0) {
                    $notification->body("{$skipped} order(s) skipped — that status isn't reachable from their current one.")->warning();
                } else {
                    $notification->success();
                }

                $notification->send();
            });
    }
}
