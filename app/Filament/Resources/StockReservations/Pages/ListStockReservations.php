<?php

namespace App\Filament\Resources\StockReservations\Pages;

use App\Filament\Resources\StockReservations\StockReservationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStockReservations extends ListRecords
{
    protected static string $resource = StockReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
