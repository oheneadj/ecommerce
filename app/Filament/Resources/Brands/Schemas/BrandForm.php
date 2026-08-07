<?php

declare(strict_types=1);

namespace App\Filament\Resources\Brands\Schemas;

use App\Actions\Catalog\ConvertImageToWebp;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Brand details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. Acme Co.')
                                    ->helperText('Brand name shown on product pages and filters. Slug will be generated automatically.')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', str($state)->slug())),

                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('auto-generated-from-name')
                                    ->unique(ignoreRecord: true),
                            ]),

                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->maxSize(config('media.max_upload_size_kb'))
                            ->disk('public')
                            ->directory('brands')
                            ->saveUploadedFileUsing(ConvertImageToWebp::forFileUpload())
                            ->helperText('Optional logo displayed on the brand page.'),

                        Textarea::make('description')
                            ->columnSpanFull()
                            ->placeholder('Short description for the brand — history, tagline, or positioning.')
                            ->helperText('Appears on brand pages and can help SEO.'),
                    ]),
            ]);
    }
}
