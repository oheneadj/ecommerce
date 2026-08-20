<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockReservations\Pages;

use App\Enums\StockReservationStatus;
use App\Filament\Resources\StockReservations\StockReservationResource;
use App\Models\StockReservation;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lists stock reservations with per-status tabs (Active, Released, Expired, etc.).
 */
class ListStockReservations extends ListRecords
{
    protected static string $resource = StockReservationResource::class;

    /**
     * @return array<int, CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * "At risk" is the tab staff need to catch quickly (a manual stock
     * correction conflicting with an active reservation).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            ...collect(StockReservationStatus::cases())->mapWithKeys(fn (StockReservationStatus $status): array => [
                $status->value => Tab::make($status->label())
                    ->query(fn (Builder $query): Builder => $query->where('status', $status))
                    ->badge(StockReservation::query()->where('status', $status)->count()),
            ])->all(),
        ];
    }
}
