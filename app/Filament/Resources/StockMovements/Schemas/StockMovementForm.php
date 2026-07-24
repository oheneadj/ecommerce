<?php

declare(strict_types=1);

namespace App\Filament\Resources\StockMovements\Schemas;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_variant_id')
                    ->label('Variant')
                    ->options(fn () => ProductVariant::query()->pluck('sku', 'id'))
                    ->searchable()
                    ->required(),

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
                    ->helperText('Positive to add stock, negative to remove it (e.g. damage).'),

                TextInput::make('note')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
