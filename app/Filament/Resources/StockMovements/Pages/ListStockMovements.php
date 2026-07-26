<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Pages;

use App\Enums\StockMovementType;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\StockMovement;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            ...collect(StockMovementType::cases())->mapWithKeys(fn (StockMovementType $type): array => [
                $type->value => Tab::make($type->label())
                    ->query(fn (Builder $query): Builder => $query->where('type', $type))
                    ->badge(StockMovement::query()->where('type', $type)->count()),
            ])->all(),
        ];
    }
}
