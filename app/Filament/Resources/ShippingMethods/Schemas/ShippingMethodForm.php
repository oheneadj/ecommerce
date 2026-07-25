<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Schemas;

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

                TextInput::make('cost')
                    ->label('Cost (pesewas)')
                    ->numeric()
                    ->required()
                    ->minValue(0),

                Toggle::make('active')
                    ->default(true),
            ]);
    }
}
