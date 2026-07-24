<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Schemas;

use App\Enums\CouponType;
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
                    ->helperText('Fixed: pesewas. Percentage: whole number (10 = 10%). Not used for free shipping.')
                    ->hidden(fn (callable $get) => $get('type') === CouponType::FreeShipping->value),

                TextInput::make('min_order_amount')
                    ->label('Minimum order amount (pesewas)')
                    ->numeric(),

                TextInput::make('usage_limit')
                    ->numeric()
                    ->helperText('Total number of times this coupon can be used, across all customers.'),

                TextInput::make('usage_limit_per_user')
                    ->numeric()
                    ->helperText('Number of times a single customer can use this coupon.'),

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
