<?php

/**
 * Super-Admin-only screen for enabling/ordering payment providers offered at checkout.
 */

declare(strict_types=1);

namespace App\Filament\Resources\PaymentProviders;

use App\Enums\UserRole;
use App\Filament\Resources\PaymentProviders\Pages\ListPaymentProviders;
use App\Filament\Resources\PaymentProviders\Tables\PaymentProviderSettingsTable;
use App\Models\PaymentProviderSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Rows aren't admin-created — `getEloquentQuery()` auto-seeds one per
 * `PaymentProvider` enum case (`PaymentProviderSetting::syncKnownProviders()`)
 * so every known provider always appears, even ones never touched.
 * List-only: no Create/Edit page, everything is done inline (toggle +
 * drag-reorder) from the table.
 */
class PaymentProviderResource extends Resource
{
    protected static ?string $model = PaymentProviderSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Payment Providers';

    public static function table(Table $table): Table
    {
        return PaymentProviderSettingsTable::configure($table);
    }

    /**
     * `getEloquentQuery()` return type carries no model generic (matches
     * `StaffResource`'s documented reasoning) — PHPStan can't resolve a
     * model generic through the base `Resource::getEloquentQuery()` here.
     */
    public static function getEloquentQuery(): Builder
    {
        PaymentProviderSetting::syncKnownProviders();

        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentProviders::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return self::isSuperAdmin();
    }

    private static function isSuperAdmin(): bool
    {
        return Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false;
    }
}
