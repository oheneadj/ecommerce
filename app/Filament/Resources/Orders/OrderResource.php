<?php

/**
 * Filament resource for managing orders — table, infolist, and status/
 * payment relation managers.
 */

declare(strict_types=1);

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\Orders\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Orders are never created via the admin panel — only CreateOrderFromCart
 * creates one, at checkout. Status updates happen via the "Update status"
 * modal action on the table/view page, not a dedicated edit page.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    /**
     * Builds the orders list table.
     */
    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    /**
     * Eager loads user and shipment to avoid N+1s on the list table.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'shipment']);
    }

    /**
     * Builds the order detail infolist.
     */
    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    /**
     * Registers the Items and Payments relation managers.
     */
    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    /**
     * Registers the resource's index/view pages.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    /**
     * Disables manual order creation — orders are only created via checkout.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Count of orders awaiting payment — same definition
     * DashboardMetricsQuery::pendingOrdersCount() uses.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->where('status', OrderStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Colors the pending-orders navigation badge as informational.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
