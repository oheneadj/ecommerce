<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Schemas;

use App\Enums\AnnouncementType;
use App\Enums\CustomerSegment;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Builds the create/edit form schema for announcements.
 */
class AnnouncementForm
{
    /**
     * Defines the announcement details and schedule form fields.
     */
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

                        Select::make('type')
                            ->options(AnnouncementType::class)
                            ->required()
                            ->native(false)
                            ->default(AnnouncementType::Banner)
                            ->helperText('A banner and a popup can both be showing at the same time — each type picks its own highest-priority announcement independently.'),

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
                                    ->helperText('Only one announcement of each type shows at a time — the highest priority currently-running one of its type wins.'),
                            ]),

                        Toggle::make('active')
                            ->default(true)
                            ->helperText('Turning this off stops it from showing immediately, without needing to touch the schedule above.'),
                    ]),
            ]);
    }
}
