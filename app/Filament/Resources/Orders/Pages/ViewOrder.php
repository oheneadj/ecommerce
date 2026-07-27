<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderRecordActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            OrderRecordActions::updateStatus(),
            OrderRecordActions::assignShipment(),
            OrderRecordActions::downloadInvoice(),
        ];
    }
}
