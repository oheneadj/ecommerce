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
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Builds the admin table for browsing and managing shipping methods.
 */
class ShippingMethodsTable
{
    /**
     * Configures columns, actions, and bulk actions for the shipping methods table.
     */
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
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete')
                        ->before(function (Collection $records): void {
                            // Same restrictOnDelete() constraint as the
                            // single-record delete (EditShippingMethod) —
                            // checked up front here too, so a bulk
                            // selection that includes even one in-use
                            // method doesn't crash with an unhandled
                            // QueryException.
                            /** @var Collection<int, ShippingMethod> $records */
                            $inUse = $records->filter(fn (ShippingMethod $method): bool => $method->shipments()->exists());

                            if ($inUse->isNotEmpty()) {
                                Notification::make()
                                    ->title('Cannot delete shipping methods')
                                    ->body("{$inUse->count()} of the selected shipping methods are still used by shipments. Deactivate them instead of deleting.")
                                    ->danger()
                                    ->send();

                                throw new Halt;
                            }
                        }),
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
