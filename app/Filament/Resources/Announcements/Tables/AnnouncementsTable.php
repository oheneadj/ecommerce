<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Tables;

use App\Enums\AnnouncementType;
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

/**
 * Builds the admin table for browsing and managing announcements.
 */
class AnnouncementsTable
{
    /**
     * Configures columns, filters, and actions for the announcements table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
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
                // Reach — the "sent-log" this feature was scoped around:
                // every distinct viewer who's ever been shown it.
                TextColumn::make('views_count')
                    ->label('Reach')
                    ->counts('views'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(AnnouncementType::class),
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
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->emptyStateHeading('No announcements yet')
            ->emptyStateDescription('Create one to show a banner on the storefront.')
            ->emptyStateIcon(Heroicon::OutlinedMegaphone)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    /**
     * Builds a bulk action that flips `active` to the given value for selected records.
     */
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
