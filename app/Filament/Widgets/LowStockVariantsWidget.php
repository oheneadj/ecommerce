<?php

/**
 * Lists the actual variants behind the dashboard's "Low Stock Items" count,
 * since a bare number gives Store Keeper/Admin nothing to act on.
 */

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Enums\VariantStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Models\ProductVariant;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class LowStockVariantsWidget extends TableWidget
{
    protected static ?string $heading = 'Low Stock Variants';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    /**
     * Same visibility as the Low Stock Items stat — Store Keeper cares
     * about this most, but Admin/Super Admin see it too.
     */
    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::StoreKeeper->value,
        ]) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ProductVariant::query()
                ->with('product')
                ->where('status', VariantStatus::Active)
                ->lowStock()
                ->orderBy('stock'))
            ->paginated(false)
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product'),
                TextColumn::make('sku')
                    ->label('SKU'),
                TextColumn::make('stock')
                    ->color('warning'),
                TextColumn::make('low_stock_threshold')
                    ->label('Threshold')
                    ->formatStateUsing(fn (ProductVariant $record): string => (string) $record->effectiveLowStockThreshold()),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View product')
                    ->url(fn (ProductVariant $record): string => ProductResource::getUrl('edit', ['record' => $record->product]))
                    ->icon('heroicon-m-arrow-top-right-on-square'),
            ])
            ->toolbarActions([
                //
            ])
            ->emptyStateHeading('No low-stock variants')
            ->emptyStateDescription('Every active variant is above its low-stock threshold.');
    }
}
