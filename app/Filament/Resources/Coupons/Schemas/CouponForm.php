<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Schemas;

use App\Enums\CouponType;
use App\Filament\Support\MoneyInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Coupon details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('code')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. SUMMER10')
                                    ->helperText('Coupon codes are case-insensitive and will be uppercased automatically.')
                                    ->unique(ignoreRecord: true)
                                    ->formatStateUsing(fn (?string $state) => $state !== null ? strtoupper($state) : null),

                                Select::make('type')
                                    ->options(CouponType::class)
                                    ->live()
                                    ->required()
                                    ->helperText('Choose a coupon type: fixed amount, percentage, or free shipping.'),

                                TextInput::make('value')
                                    ->numeric()
                                    ->required(fn (callable $get): bool => $get('type') !== CouponType::FreeShipping)
                                    // Negative never makes sense for either type (it would increase
                                    // the order total instead of discounting it); a Percentage above
                                    // 100 would discount more than the order is worth — Fixed has no
                                    // upper bound here since ApplyCouponToOrder already caps it at
                                    // the order's own subtotal.
                                    ->minValue(0)
                                    ->maxValue(fn (callable $get): ?int => $get('type') === CouponType::Percentage ? 100 : null)
                                    ->prefix(fn (callable $get): ?string => $get('type') === CouponType::Fixed ? 'GH₵' : null)
                                    ->placeholder(fn (callable $get): string => $get('type') === CouponType::Percentage ? 'e.g. 15 for 15%' : 'e.g. 10.00 for GH₵10')
                                    ->helperText('Fixed: entered in Cedis. Percentage: whole number (10 = 10%). Not used for free shipping.')
                                    ->hidden(fn (callable $get) => $get('type') === CouponType::FreeShipping)
                                    // Only a Fixed coupon's value is money — Percentage stores a
                                    // plain whole-number rate, so it must never be multiplied/divided
                                    // by 100 like every other money field here. The `type` field casts
                                    // to the CouponType enum itself, not its string ->value — compare
                                    // against the enum case directly, not ->value.
                                    ->afterStateHydrated(function (TextInput $component, mixed $state, callable $get): void {
                                        if ($state !== null && $get('type') === CouponType::Fixed) {
                                            $component->state(round(((float) $state) / 100, 2));
                                        }
                                    })
                                    ->dehydrateStateUsing(function (mixed $state, callable $get): mixed {
                                        if ($state === null || $state === '') {
                                            return null;
                                        }

                                        return $get('type') === CouponType::Fixed
                                            ? (int) round(((float) $state) * 100)
                                            : $state;
                                    }),

                                DateTimePicker::make('expires_at')
                                    ->label('Expiration date')
                                    ->helperText('Leave empty to keep the coupon valid indefinitely.'),

                                Toggle::make('active')
                                    ->default(true)
                                    ->helperText('Toggle off to pause the coupon without deleting it.'),
                            ]),
                    ]),

                Section::make('Usage limits')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                MoneyInput::make('min_order_amount')
                                    ->label('Minimum order amount')
                                    ->minValue(0)
                                    ->placeholder('e.g. 50.00')
                                    ->helperText('Leave blank to apply to all orders.'),

                                TextInput::make('usage_limit')
                                    ->numeric()
                                    ->minValue(1)
                                    ->placeholder('e.g. 100')
                                    ->helperText('Total number of times this coupon can be redeemed. Leave blank for unlimited — use the Active toggle to disable a coupon, not 0.'),

                                TextInput::make('usage_limit_per_user')
                                    ->numeric()
                                    ->minValue(1)
                                    ->placeholder('e.g. 1')
                                    ->helperText('How many times the same customer may use this coupon. Leave blank for unlimited.'),
                            ]),
                    ]),

                Section::make('Scope')
                    ->schema([
                        Select::make('products')
                            ->relationship('products', 'name')
                            ->multiple()
                            ->searchable()
                            ->helperText('Leave both this and Categories empty for a cart-wide coupon.'),

                        Select::make('categories')
                            ->relationship('categories', 'name')
                            ->multiple()
                            ->searchable()
                            ->helperText('Restrict the coupon to these categories only.'),
                    ]),
            ]);
    }
}
