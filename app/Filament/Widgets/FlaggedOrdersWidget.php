<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Queries\DashboardMetricsQuery;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\Auth;

/**
 * "Stuck" is always judged against now() regardless of the dashboard's
 * date-range filter — see DashboardMetricsQuery::flaggedOrdersQuery()'s
 * docblock. The filter only scopes *which orders* (by creation date) are
 * considered here at all.
 */
class FlaggedOrdersWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Flagged Orders';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    /**
     * Visible to Admins/Super Admins only.
     */
    public static function canView(): bool
    {
        return Auth::user()?->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Admin->value]) ?? false;
    }

    /**
     * Build the flagged-orders table, filtered by the dashboard's date range if set.
     */
    public function table(Table $table): Table
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        return $table
            ->query(fn () => app(DashboardMetricsQuery::class)->flaggedOrdersQuery($startDate, $endDate))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('order_number')
                    ->label('Number'),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->placeholder(fn (Order $record) => $record->guest_email ?? 'Guest'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('grand_total_formatted')
                    ->label('Total price'),
                TextColumn::make('created_at')
                    ->label('Days Old')
                    ->formatStateUsing(fn (Order $record): int => (int) $record->created_at->diffInDays(now())),
                TextColumn::make('issue')
                    ->label('Issue')
                    ->badge()
                    ->state(fn (Order $record): string => match ($record->status) {
                        OrderStatus::Processing => 'Stuck in processing',
                        default => 'Awaiting processing',
                    })
                    ->color(fn (Order $record): string => match ($record->status) {
                        OrderStatus::Processing => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                //
            ])
            ->emptyStateHeading('No flagged orders')
            ->emptyStateDescription('No orders are stuck in processing or awaiting action.');
    }
}
