<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderRecordActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

/**
 * Order detail page — renders the order infolist and status/shipment/
 * invoice header actions.
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * Builds the order detail infolist.
     */
    public function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    /**
     * Registers the header actions for the view page.
     */
    protected function getHeaderActions(): array
    {
        return [
            OrderRecordActions::updateStatus(),
            OrderRecordActions::assignShipment(),
            OrderRecordActions::downloadInvoice(),
            OrderRecordActions::regenerateInvoice(),
        ];
    }
}
