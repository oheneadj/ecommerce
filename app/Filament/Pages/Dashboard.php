<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard\Actions\FilterAction;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersAction;

/**
 * Filters dashboard widget data via an action modal (rather than an
 * always-visible filters form) — widgets read the chosen date range through
 * InteractsWithPageFilters and fall back to their own default window
 * (today/this month/last 6 months) when no range has been applied.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersAction;

    protected function getHeaderActions(): array
    {
        return [
            FilterAction::make()
                ->schema([
                    DatePicker::make('startDate')
                        ->label('From')
                        ->native(false)
                        ->placeholder('Today')
                        ->default(now()->toDateString()),

                    DatePicker::make('endDate')
                        ->label('To')
                        ->native(false)
                        ->placeholder('Today')
                        ->default(now()->toDateString())
                        ->afterOrEqual('startDate'),
                ]),
        ];
    }
}
