<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaticPages\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StaticPageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page content')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. About Us')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', str($state)->slug())),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('auto-generated-from-title')
                            ->helperText('The intended public URL segment once the storefront renders this page.')
                            ->unique(ignoreRecord: true),

                        RichEditor::make('content')
                            ->columnSpanFull(),

                        Toggle::make('is_published')
                            ->default(false)
                            ->helperText('Draft pages are only visible in this admin panel.'),
                    ]),

                Section::make('SEO')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('meta_title')
                                    ->maxLength(255)
                                    ->placeholder('Optional: SEO title (auto-filled from title)')
                                    ->helperText('Auto-filled from the page title when left blank.'),

                                TextInput::make('meta_description')
                                    ->maxLength(255)
                                    ->placeholder('Optional: SEO description'),
                            ]),
                    ]),
            ]);
    }
}
