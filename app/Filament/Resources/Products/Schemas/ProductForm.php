<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Brand;
use App\Models\Category;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', str($state)->slug())),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Select::make('category_id')
                    ->label('Category')
                    ->options(fn () => Category::query()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),

                Select::make('brand_id')
                    ->label('Brand')
                    ->options(fn () => Brand::query()->pluck('name', 'id'))
                    ->searchable(),

                Select::make('status')
                    ->options(ProductStatus::class)
                    ->required()
                    ->default(ProductStatus::Draft),

                Textarea::make('description')
                    ->columnSpanFull(),

                TextInput::make('meta_title')
                    ->maxLength(255),

                TextInput::make('meta_description')
                    ->maxLength(255),

                Repeater::make('variants')
                    ->label('Variants')
                    ->statePath('variants')
                    ->schema([
                        TextInput::make('sku')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('price')
                            ->label('Price (pesewas)')
                            ->numeric()
                            ->required()
                            ->minValue(0),

                        TextInput::make('stock')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(0),

                        Select::make('status')
                            ->options(VariantStatus::class)
                            ->required()
                            ->default(VariantStatus::Active),
                    ])
                    ->columns(4)
                    ->required()
                    ->minItems(1)
                    ->hiddenOn('edit')
                    ->columnSpanFull()
                    ->addActionLabel('Add variant'),
            ]);
    }
}
