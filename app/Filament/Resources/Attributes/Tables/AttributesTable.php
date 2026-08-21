<?php

declare(strict_types=1);

namespace App\Filament\Resources\Attributes\Tables;

use App\Models\Attribute;
use App\Support\AttributeUsageSummary;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/** Builds the attributes list table: columns, actions, and bulk delete blocked while any selected attribute is still in use. */
class AttributesTable
{
    /** Configures the attributes list table. */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('terms_count')
                    ->label('Values')
                    ->counts('terms'),
                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products'),
                TextColumn::make('created_at')
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete')
                        ->requiresConfirmation()
                        ->before(function (Collection $records): void {
                            /** @var Collection<int, Attribute> $records */
                            $message = AttributeUsageSummary::forBlockedDelete($records);

                            if ($message === null) {
                                return;
                            }

                            Notification::make()
                                ->title('Cannot delete attributes')
                                ->body($message)
                                ->danger()
                                ->send();

                            throw new Halt;
                        }),
                ]),
            ])
            ->emptyStateHeading('No attributes yet')
            ->emptyStateDescription('Create an attribute (e.g. Size, Color) to start reusing it across products.')
            ->emptyStateIcon(Heroicon::OutlinedTag)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
