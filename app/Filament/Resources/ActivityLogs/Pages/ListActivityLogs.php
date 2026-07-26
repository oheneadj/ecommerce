<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs\Pages;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'created' => Tab::make('Created')
                ->query(fn (Builder $query): Builder => $query->where('event', 'created'))
                ->badge(Activity::query()->where('event', 'created')->count()),
            'updated' => Tab::make('Updated')
                ->query(fn (Builder $query): Builder => $query->where('event', 'updated'))
                ->badge(Activity::query()->where('event', 'updated')->count()),
            'deleted' => Tab::make('Deleted')
                ->query(fn (Builder $query): Builder => $query->where('event', 'deleted'))
                ->badge(Activity::query()->where('event', 'deleted')->count()),
        ];
    }
}
