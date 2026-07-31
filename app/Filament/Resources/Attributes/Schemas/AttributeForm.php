<?php

declare(strict_types=1);

namespace App\Filament\Resources\Attributes\Schemas;

use App\Enums\AttributeType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attribute details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Size, Color')
                                    ->helperText('Slug will be generated automatically.')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', str($state)->slug()))
                                    ->unique(ignoreRecord: true),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('auto-generated-from-name')
                                    ->unique(ignoreRecord: true),

                                Select::make('type')
                                    ->options(AttributeType::class)
                                    ->required()
                                    ->default(AttributeType::Text)
                                    ->helperText('Text is a plain list of values; Color/Image let each value carry a swatch.'),
                            ]),
                    ]),
            ]);
    }
}
