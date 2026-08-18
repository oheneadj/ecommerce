<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Schemas;

use App\Enums\CustomerSegment;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Announcement')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Black Friday Sale'),

                        Textarea::make('body')
                            ->required()
                            ->maxLength(500)
                            ->rows(3)
                            ->placeholder('e.g. 20% off everything, this weekend only.'),

                        Select::make('audience')
                            ->options(CustomerSegment::class)
                            ->required()
                            ->native(false)
                            ->default(CustomerSegment::All)
                            ->helperText('Guests only ever match "All customers" — the other segments need order/account history a guest doesn\'t have.'),
                    ]),

                Section::make('Schedule')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                DateTimePicker::make('starts_at')
                                    ->required()
                                    ->default(now())
                                    ->native(false),

                                DateTimePicker::make('ends_at')
                                    ->native(false)
                                    ->helperText('Leave blank to run indefinitely, until turned off by hand.'),

                                TextInput::make('priority')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->helperText('Only one announcement shows at a time — the highest priority currently-running one wins.'),
                            ]),

                        Toggle::make('active')
                            ->default(true)
                            ->helperText('Turning this off stops it from showing immediately, without needing to touch the schedule above.'),
                    ]),
            ]);
    }
}
