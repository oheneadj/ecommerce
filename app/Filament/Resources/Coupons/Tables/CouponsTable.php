<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Tables;

use App\Enums\CouponType;
use App\Models\Coupon;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('usages_count')
                    ->label('Uses')
                    ->counts('usages'),
                TextColumn::make('usage_limit')
                    ->placeholder('Unlimited'),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->placeholder('Never')
                    ->sortable(),
                IconColumn::make('active')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(CouponType::class),
                TernaryFilter::make('active'),
            ])
            ->recordActions([
                Action::make('copyCode')
                    ->label('Copy code')
                    ->icon(Heroicon::OutlinedClipboard)
                    ->color('gray')
                    ->alpineClickHandler(fn (Coupon $record): string => '
                        navigator.clipboard.writeText('.json_encode($record->code).').then(() => {
                            new FilamentNotification().title('.json_encode(__('Coupon code copied')).').success().send();
                        }).catch(() => {
                            new FilamentNotification().title('.json_encode(__('Could not copy code')).').danger().send();
                        });
                    '),
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
            ->emptyStateHeading('No coupons yet')
            ->emptyStateDescription('Create a coupon to offer discounts at checkout.')
            ->emptyStateIcon(Heroicon::OutlinedTicket)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }

    /**
     * No single-record equivalent existed before this — `active` was
     * previously only editable through the edit form. Authorized the
     * same way as the edit form itself (CouponPolicy::update()).
     */
    private static function toggleActiveBulkAction(string $name, bool $active): BulkAction
    {
        return BulkAction::make($name)
            ->label(ucfirst($name))
            ->authorizeIndividualRecords('update')
            ->requiresConfirmation()
            ->action(function (Collection $records) use ($active): void {
                foreach ($records as $record) {
                    if ($record instanceof Coupon) {
                        $record->update(['active' => $active]);
                    }
                }

                Notification::make()->title('Coupons updated')->success()->send();
            });
    }
}
