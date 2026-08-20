<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\RelationManagers\ApiLogsRelationManager;
use App\Filament\Resources\Payments\RelationManagers\RefundsRelationManager;
use App\Filament\Resources\Payments\Schemas\PaymentInfolist;
use App\Filament\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Payments are never created or edited via the admin panel — only
 * InitiatePayment creates one, and only HandlePaymentWebhook/
 * VerifyPendingPayments/HandleLatePaymentConfirmation ever change its
 * status. The only admin action available is issuing a refund.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    /**
     * Configures the payments list table.
     */
    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    /**
     * Eager loads the order relation for the list table.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['order']);
    }

    /**
     * Configures the payment detail infolist.
     */
    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    /**
     * Registers the refunds and API logs relation managers.
     */
    public static function getRelations(): array
    {
        return [
            RefundsRelationManager::class,
            ApiLogsRelationManager::class,
        ];
    }

    /**
     * Registers the pages available on this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    /**
     * Payments are never created via the admin panel.
     */
    public static function canCreate(): bool
    {
        return false;
    }
}
