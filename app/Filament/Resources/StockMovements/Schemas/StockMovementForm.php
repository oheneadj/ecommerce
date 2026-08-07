<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Schemas;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Stock movement')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('product_variant_id')
                                    ->label('Variant')
                                    ->options(fn () => ProductVariant::query()->pluck('sku', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->helperText('Select the variant to adjust stock for.'),

                                Select::make('type')
                                    ->options([
                                        StockMovementType::Restock->value => StockMovementType::Restock->label(),
                                        StockMovementType::Adjustment->value => StockMovementType::Adjustment->label(),
                                        StockMovementType::Return->value => StockMovementType::Return->label(),
                                        StockMovementType::Damage->value => StockMovementType::Damage->label(),
                                    ])
                                    ->helperText('Sales are recorded automatically on payment confirmation, never entered manually here.')
                                    ->required(),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->required()
                                    ->rules(['not_in:0'])
                                    ->placeholder('e.g. 10 or -3')
                                    ->helperText('Positive adds stock, negative removes stock. Cannot be zero.'),

                                TextInput::make('note')
                                    ->maxLength(255)
                                    ->placeholder('Optional internal note for this stock movement.'),
                            ]),
                    ]),
            ]);
    }
}
