<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentProviders\Tables;

use App\Actions\Catalog\ConvertImageToWebp;
use App\Enums\PaymentProvider;
use App\Enums\PaystackCheckoutMode;
use App\Models\PaymentProviderSetting;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PaymentProviderSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('')
                    ->disk('public')
                    ->defaultImageUrl(fn (PaymentProviderSetting $record): string => 'https://ui-avatars.com/api/?name='.urlencode($record->provider->label()).'&background=random')
                    ->size(32),
                TextColumn::make('provider')
                    ->label('Provider')
                    ->formatStateUsing(fn (PaymentProviderSetting $record): string => $record->provider->label())
                    ->description(fn (PaymentProviderSetting $record): ?string => $record->description)
                    ->badge(),
                IconColumn::make('credentials_configured')
                    ->label('Credentials set')
                    ->state(fn (PaymentProviderSetting $record): bool => $record->provider->hasCredentialsConfigured())
                    ->boolean(),
                TextColumn::make('checkout_mode')
                    ->label('Checkout mode')
                    ->formatStateUsing(fn (PaymentProviderSetting $record): ?string => $record->provider === PaymentProvider::Paystack
                        ? ($record->checkout_mode?->label() ?? PaystackCheckoutMode::Redirect->label())
                        : null)
                    ->placeholder('—')
                    ->badge(),
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
            ->recordActions([
                EditAction::make()
                    ->button()
                    ->schema(fn (PaymentProviderSetting $record): array => [
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->maxSize(config('media.max_upload_size_kb'))
                            ->disk('public')
                            ->directory('payment-providers')
                            ->saveUploadedFileUsing(ConvertImageToWebp::forFileUpload())
                            ->helperText('Shown in this list and at checkout next to the provider name.'),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(255)
                            ->helperText('Optional short note shown next to the provider name here — e.g. "Card payments via Paystack".'),
                        // Only Paystack has a checkout mode to choose — omitted
                        // entirely (not just ->visible(false)) for every other
                        // provider, since visible() alone would still leave the
                        // field present in the schema, just hidden from view.
                        ...($record->provider === PaymentProvider::Paystack ? [
                            Radio::make('checkout_mode')
                                ->label('Checkout mode')
                                ->options(PaystackCheckoutMode::class)
                                ->default(PaystackCheckoutMode::Redirect)
                                ->helperText('Redirect sends the customer to Paystack\'s own page. Popup keeps them on this site.'),
                        ] : []),
                    ]),
            ])
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
