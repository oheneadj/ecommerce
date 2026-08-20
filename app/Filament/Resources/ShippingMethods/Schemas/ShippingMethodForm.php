<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Schemas;

use App\Filament\Support\MoneyInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Builds the create/edit form schema for shipping methods.
 */
class ShippingMethodForm
{
    /**
     * Defines the shipping method details form fields (name, cost, active).
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Shipping method details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Standard shipping')
                                    ->helperText('Visible shipping method name shown at checkout.'),

                                MoneyInput::make('cost')
                                    ->label('Cost')
                                    ->required()
                                    ->minValue(0)
                                    ->placeholder('e.g. 5.00')
                                    ->helperText('Use 0 for free shipping.'),

                                Toggle::make('active')
                                    ->default(true)
                                    ->helperText('Disabled shipping methods are hidden from checkout.'),
                            ]),
                    ]),
            ]);
    }
}
