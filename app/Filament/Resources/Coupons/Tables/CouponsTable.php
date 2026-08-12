<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Tables;

use App\Enums\CouponType;
use App\Models\Coupon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

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
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No coupons yet')
            ->emptyStateDescription('Create a coupon to offer discounts at checkout.')
            ->emptyStateIcon(Heroicon::OutlinedTicket)
            ->emptyStateActions([
                CreateAction::make(),
            ]);
    }
}
