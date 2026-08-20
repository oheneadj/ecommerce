<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard widget listing the 10 most recent orders, scoped to the
 * dashboard's optional date-range filter.
 */
class RecentOrdersWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Recent Orders';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    /**
     * Visible to Admins/Super Admins only.
     */
    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
    }

    /**
     * Build the recent-orders table, filtered by the dashboard's date range if set.
     */
    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        return $table
            ->query(function () use ($startDate, $endDate): Builder {
                $query = Order::query()->latest();

                if ($startDate) {
                    $query->whereDate('created_at', '>=', $startDate);
                }

                if ($endDate) {
                    $query->whereDate('created_at', '<=', $endDate);
                }

                return $query->limit(10);
            })
            ->paginated(false)
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('order_number'),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->placeholder(fn (Order $record) => $record->guest_email ?? 'Guest'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('grand_total_formatted')
                    ->label('Total'),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ]);
    }
}
