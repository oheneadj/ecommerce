<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. T-Shirts')
                                    ->helperText('Category name displayed in navigation and filters. Slug will be generated automatically.')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', str($state)->slug())),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('auto-generated-from-name')
                                    ->unique(ignoreRecord: true),

                                Select::make('parent_id')
                                    ->label('Parent category')
                                    ->options(fn () => Category::query()->pluck('name', 'id'))
                                    ->searchable(),
                            ]),
                    ]),
            ]);
    }
}
