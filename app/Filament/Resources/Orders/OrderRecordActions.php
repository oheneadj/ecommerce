<?php

/**
 * The record-scoped actions shared between the Orders table row and the
 * order's own view page — extracted so both surfaces stay in sync instead
 * of maintaining two copies of the same modal.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Orders;

use App\Actions\Order\AssignShipment;
use App\Actions\Order\GenerateOrderInvoice;
use App\Actions\Order\UpdateOrderStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ShippingMethod;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class OrderRecordActions
{
    /**
     * A modal (not a full edit-page navigation) so updating an order's
     * status stays a quick in-place action.
     */
    public static function updateStatus(): Action
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

    public static function assignShipment(): Action
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

    /**
     * `invoice_path` being set doesn't guarantee the file is still actually
     * on disk (e.g. storage lost/reset without the DB following along) —
     * regenerated on the fly rather than a raw 500 if it's gone.
     * `GenerateOrderInvoice` renders exclusively from the order's own
     * permanently-snapshotted data, so this is always safe to re-run and
     * produces an identical result to the original.
     */
    public static function downloadInvoice(): Action
    {
        return Action::make('downloadInvoice')
            ->label('Download invoice')
            ->visible(fn (Order $record) => $record->invoice_path !== null)
            ->action(function (Order $record) {
                if (Storage::disk('local')->missing($record->invoice_path)) {
                    GenerateOrderInvoice::run($record);
                    $record->refresh();
                }

                return Storage::disk('local')->download($record->invoice_path, "{$record->order_number}.pdf");
            });
    }
}
