<?php

declare(strict_types=1);

namespace App\Filament\Resources\StaticPages\Tables;

use App\Models\StaticPage;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the static pages list table: columns, actions, and bulk
 * publish/unpublish toggles.
 */
class StaticPagesTable
{
    /** Configures the static pages list table. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make()
                    ->button(),
                Action::make('preview')
                    ->label('Preview')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::togglePublishedBulkAction('publish', true),
                    self::togglePublishedBulkAction('unpublish', false),
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->emptyStateHeading('No pages yet')
            ->emptyStateDescription('Create a content page like About, Contact, or Terms.')
            ->emptyStateIcon(Heroicon::OutlinedDocumentText)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    /**
     * No single-record equivalent existed before this — `is_published`
     * was previously only editable through the edit form.
     */
    private static function togglePublishedBulkAction(string $name, bool $published): BulkAction
    {
        return BulkAction::make($name)
            ->label(ucfirst($name))
            ->authorizeIndividualRecords('update')
            ->requiresConfirmation()
            ->action(function (Collection $records) use ($published): void {
                foreach ($records as $record) {
                    if ($record instanceof StaticPage) {
                        $record->update(['is_published' => $published]);
                    }
                }

                Notification::make()->title('Pages updated')->success()->send();
            });
    }
}
