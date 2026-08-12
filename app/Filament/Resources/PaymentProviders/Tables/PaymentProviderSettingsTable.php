<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentProviders\Tables;

use App\Models\PaymentProviderSetting;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PaymentProviderSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('provider')
                    ->label('Provider')
                    ->formatStateUsing(fn (PaymentProviderSetting $record): string => $record->provider->label())
                    ->badge(),
                IconColumn::make('credentials_configured')
                    ->label('Credentials set')
                    ->state(fn (PaymentProviderSetting $record): bool => $record->provider->hasCredentialsConfigured())
                    ->boolean(),
                ToggleColumn::make('enabled')
                    ->label('Enabled')
                    ->disabled(fn (PaymentProviderSetting $record): bool => self::cannotToggle($record))
                    ->tooltip(fn (PaymentProviderSetting $record): ?string => match (true) {
                        ! $record->provider->hasCredentialsConfigured() => 'No credentials configured in the environment yet.',
                        $record->enabled => 'At least one payment provider must stay enabled.',
                        default => null,
                    }),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('No payment providers')
            ->emptyStateDescription('Providers appear here automatically as they\'re added to the codebase.');
    }

    /**
     * A toggle is locked (can't be flipped either direction) when: its
     * provider has no `.env` credentials at all, or it's the only
     * currently-enabled provider — checkout would otherwise have nothing
     * to offer.
     */
    private static function cannotToggle(PaymentProviderSetting $record): bool
    {
        if (! $record->provider->hasCredentialsConfigured()) {
            return true;
        }

        if (! $record->enabled) {
            return false;
        }

        return ! PaymentProviderSetting::query()->where('enabled', true)->whereKeyNot($record->getKey())->exists();
    }
}
