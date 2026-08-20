<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Widgets\OrdersOverviewWidget;
use App\Models\Order;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Order list page — status tabs plus the overview widget.
 */
class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * Registers the "create order" header action.
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Registers the orders overview widget shown above the table.
     */
    protected function getHeaderWidgets(): array
    {
        return [
            OrdersOverviewWidget::class,
        ];
    }

    /**
     * One tab per order status, so staff can jump straight to (e.g.) pending
     * orders without setting up the status filter every time.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            ...collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $status): array => [
                $status->value => Tab::make($status->label())
                    ->query(fn (Builder $query): Builder => $query->where('status', $status))
                    ->badge(Order::query()->where('status', $status)->count()),
            ])->all(),
        ];
    }
}
