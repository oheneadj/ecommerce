<?php

declare(strict_types=1);

namespace App\Filament\Resources\ActivityLogs\Tables;

use Filament\Actions\Action;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('causer.name')
                    ->label('Who')
                    ->placeholder('System'),
                TextColumn::make('log_name')
                    ->label('Record type')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('description'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('log_name')
                    ->options(fn () => Activity::query()->distinct()->pluck('log_name', 'log_name')),
                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
            ])
            ->recordActions([
                self::viewChangesAction(),
            ])
            ->toolbarActions([
                //
            ])
            ->emptyStateHeading('No activity recorded yet')
            ->emptyStateDescription('Admin actions on key records will appear here as they happen.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList);
    }

    private static function viewChangesAction(): Action
    {
        return Action::make('viewChanges')
            ->label('View changes')
            ->modalHeading('Changed attributes')
            ->schema([
                TextEntry::make('description'),
                KeyValueEntry::make('changes')
                    ->label('Before / after')
                    ->state(fn (Activity $record) => self::formatChanges($record)),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function formatChanges(Activity $record): array
    {
        $old = (array) ($record->properties['old'] ?? []);
        $attributes = (array) ($record->properties['attributes'] ?? []);

        $formatted = [];

        foreach ($attributes as $key => $newValue) {
            $oldValue = $old[$key] ?? null;
            $formatted[$key] = json_encode($oldValue).' → '.json_encode($newValue);
        }

        return $formatted;
    }
}
