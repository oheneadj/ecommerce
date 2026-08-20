<?php

declare(strict_types=1);

namespace App\Filament\Resources\Customers\Pages;

use App\Filament\Resources\Customers\CustomerRecordActions;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Schemas\CustomerInfolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

/**
 * Customer detail page — renders the customer infolist plus send-email/
 * send-SMS header actions.
 */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    /**
     * Builds the customer detail infolist.
     */
    public function infolist(Schema $schema): Schema
    {
        return CustomerInfolist::configure($schema);
    }

    /**
     * Registers the header actions for the view page.
     */
    protected function getHeaderActions(): array
    {
        return [
            CustomerRecordActions::sendEmail(),
            CustomerRecordActions::sendSms(),
        ];
    }
}
