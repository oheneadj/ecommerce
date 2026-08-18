<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Tables;

use App\Enums\CustomerSegment;
use App\Models\Announcement;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('audience')
                    ->badge(),
                IconColumn::make('active')
                    ->boolean(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('priority')
                    ->sortable(),
                // Reach/dismiss counts — the "sent-log" this feature was
                // scoped around. views_count is every viewer who's ever
                // been shown it; dismissed_count (a filtered aggregate,
                // see AnnouncementResource::getEloquentQuery()) is how
                // many of those actively closed it.
                TextColumn::make('views_count')
                    ->label('Views')
                    ->counts('views'),
                TextColumn::make('dismissed_count')
                    ->label('Dismissed'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('audience')
                    ->options(CustomerSegment::class),
            ])
            ->recordActions([
                EditAction::make()
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::toggleActiveBulkAction('activate', true),
                    self::toggleActiveBulkAction('deactivate', false),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No announcements yet')
            ->emptyStateDescription('Create one to show a banner on the storefront.')
            ->emptyStateIcon(Heroicon::OutlinedMegaphone)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    private static function toggleActiveBulkAction(string $name, bool $active): BulkAction
    {
        return BulkAction::make($name)
            ->label(ucfirst($name))
            ->authorizeIndividualRecords('update')
            ->requiresConfirmation()
            ->action(function (Collection $records) use ($active): void {
                foreach ($records as $record) {
                    if ($record instanceof Announcement) {
                        $record->update(['active' => $active]);
                    }
                }

                Notification::make()->title('Announcements updated')->success()->send();
            });
    }
}
