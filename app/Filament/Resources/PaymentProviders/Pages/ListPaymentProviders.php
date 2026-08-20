<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentProviders\Pages;

use App\Filament\Resources\PaymentProviders\PaymentProviderResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Payment providers index page — configuration only, no create action.
 */
class ListPaymentProviders extends ListRecords
{
    protected static string $resource = PaymentProviderResource::class;

    /**
     * No header actions — providers are seeded, not created via the panel.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
