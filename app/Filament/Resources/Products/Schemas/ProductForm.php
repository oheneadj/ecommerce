<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Models\Brand;
use App\Models\Category;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Classic White Tee')
                                    ->helperText('Short, descriptive product name. Slug and meta title will be generated automatically if left blank.')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (string $state, callable $set, callable $get): void {
                                        $set('slug', str($state)->slug());

                                        $currentMeta = $get('meta_title');
                                        if (empty($currentMeta) && ! empty($state)) {
                                            $set('meta_title', str($state)->limit(60));
                                        }
                                    }),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('auto-generated-from-name')
                                    ->unique(ignoreRecord: true),

                                Select::make('category_id')
                                    ->label('Category')
                                    ->options(fn () => Category::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->helperText('Select the primary category for this product.'),

                                Select::make('brand_id')
                                    ->label('Brand')
                                    ->options(fn () => Brand::query()->pluck('name', 'id'))
                                    ->searchable()
                                    ->helperText('Optional: associate a brand for filtering and display.'),

                                Select::make('status')
                                    ->options(ProductStatus::class)
                                    ->required()
                                    ->default(ProductStatus::Draft),
                                Textarea::make('description')
                                    ->columnSpanFull()
                                    ->placeholder('Describe the product — features, materials, sizing, and care instructions.')
                                    ->helperText('Write a concise, scannable product description; used for product pages and can seed the meta description.')
                                    ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                                        $current = $get('meta_description');
                                        if (empty($current) && ! empty($state)) {
                                            $set('meta_description', str($state)->limit(160));
                                        }
                                    }),
                            ]),
                    ]),

                Section::make('Variants')
                    ->schema([
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

                                TextInput::make('low_stock_threshold')
                                    ->numeric()
                                    ->minValue(0)
                                    ->helperText('Leave blank to use the store-wide default.'),

                                Select::make('status')
                                    ->options(VariantStatus::class)
                                    ->required()
                                    ->default(VariantStatus::Active),

                                Repeater::make('attributeValues')
                                    ->label('Attributes')
                                    ->schema([
                                        TextInput::make('attribute_name')
                                            ->label('Name')
                                            ->placeholder('e.g. Size, Color')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('value')
                                            ->placeholder('e.g. Large, Red')
                                            ->required()
                                            ->maxLength(255),
                                    ])
                                    ->columns(2)
                                    ->addActionLabel('Add attribute')
                                    ->addAction(fn (Action $action) => $action->color('primary'))
                                    ->helperText('Free-form — a variant can mix any attributes it needs (e.g. both Size and Color).')
                                    ->columnSpanFull(),
                            ])
                            ->columns(4)
                            ->hiddenOn('edit')
                            ->columnSpanFull()
                            ->addActionLabel('Add variant')
                            ->helperText('Optional for a Draft product — add variants later via the Variants tab. Required before the product can be set to Active.'),
                    ]),

                Section::make('SEO')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('meta_title')
                                    ->maxLength(255)
                                    ->placeholder('Optional: SEO title (auto-filled from name)')
                                    ->helperText('Auto-filled from the product name when left blank.'),

                                TextInput::make('meta_description')
                                    ->maxLength(255)
                                    ->placeholder('Optional: SEO description (auto-filled from description)')
                                    ->helperText('Auto-filled from the product description when left blank.'),
                            ]),
                    ]),

            ]);
    }
}
