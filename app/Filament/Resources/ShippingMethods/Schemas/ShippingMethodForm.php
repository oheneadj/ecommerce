<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Schemas;

use App\Filament\Support\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ShippingMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                MoneyInput::make('cost')
                    ->label('Cost')
                    ->required()
                    ->minValue(0),

                Toggle::make('active')
                    ->default(true),
            ]);
    }
}
