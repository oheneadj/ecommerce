<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShippingMethods\Tables;

use App\Models\ShippingMethod;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ShippingMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cost_formatted')
                    ->label('Cost')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('cost', $direction === 'desc' ? 'desc' : 'asc')),
                IconColumn::make('active')
                    ->boolean(),
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
                    self::toggleActiveBulkAction('activate', true),
                    self::toggleActiveBulkAction('deactivate', false),
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ])
            ->emptyStateHeading('No shipping methods yet')
            ->emptyStateDescription('Create a shipping method to offer at checkout.')
            ->emptyStateIcon(Heroicon::OutlinedTruck)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    /**
     * No single-record equivalent existed before this — `active` was
     * previously only editable through the edit form, same gap Coupon
     * had (mirrors CouponsTable::toggleActiveBulkAction() exactly).
     */
    private static function toggleActiveBulkAction(string $name, bool $active): BulkAction
    {
        return BulkAction::make($name)
            ->label(ucfirst($name))
            ->authorizeIndividualRecords('update')
            ->requiresConfirmation()
            ->action(function (Collection $records) use ($active): void {
                foreach ($records as $record) {
                    if ($record instanceof ShippingMethod) {
                        $record->update(['active' => $active]);
                    }
                }

                Notification::make()->title('Shipping methods updated')->success()->send();
            });
    }
}
