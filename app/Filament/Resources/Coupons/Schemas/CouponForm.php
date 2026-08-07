<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Schemas;

use App\Enums\CouponType;
use App\Filament\Support\MoneyInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->formatStateUsing(fn (?string $state) => $state !== null ? strtoupper($state) : null),

                Select::make('type')
                    ->options(CouponType::class)
                    ->live()
                    ->required(),

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
                    ->helperText('Fixed: entered in Cedis. Percentage: whole number (10 = 10%). Not used for free shipping.')
                    ->hidden(fn (callable $get) => $get('type') === CouponType::FreeShipping)
                    // Only a Fixed coupon's value is money — Percentage stores a
                    // plain whole-number rate, so it must never be multiplied/divided
                    // by 100 like every other money field here. The `type` field casts
                    // to the CouponType enum itself, not its string ->value — compare
                    // against the enum case directly, not ->value (a pre-existing bug in
                    // the ->hidden() check above, fixed here too).
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

                MoneyInput::make('min_order_amount')
                    ->label('Minimum order amount')
                    ->minValue(0),

                TextInput::make('usage_limit')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Total number of times this coupon can be used, across all customers. Leave blank for unlimited — use the Active toggle to disable a coupon, not 0.'),

                TextInput::make('usage_limit_per_user')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Number of times a single customer can use this coupon. Leave blank for unlimited.'),

                DateTimePicker::make('expires_at'),

                Toggle::make('active')
                    ->default(true),

                Select::make('products')
                    ->relationship('products', 'name')
                    ->multiple()
                    ->searchable()
                    ->helperText('Leave both this and Categories empty for a cart-wide coupon.'),

                Select::make('categories')
                    ->relationship('categories', 'name')
                    ->multiple()
                    ->searchable(),
            ]);
    }
}
