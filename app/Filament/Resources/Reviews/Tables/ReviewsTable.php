<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews\Tables;

use App\Actions\Review\ModerateReview;
use App\Enums\ReviewStatus;
use App\Models\Review;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->sortable(),
                TextColumn::make('rating')
                    ->sortable(),
                TextColumn::make('title')
                    ->placeholder('—'),
                TextColumn::make('body')
                    ->limit(60),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ReviewStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                self::moderateAction('approve', ReviewStatus::Approved),
                self::moderateAction('reject', ReviewStatus::Rejected),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No reviews yet')
            ->emptyStateDescription('Reviews will appear here once customers start reviewing their purchases.')
            ->emptyStateIcon(Heroicon::OutlinedStar);
    }

    private static function moderateAction(string $name, ReviewStatus $status): Action
    {
        return Action::make($name)
            ->label(ucfirst($name))
            ->visible(fn (Review $record) => $record->status !== $status)
            ->authorize(fn (): bool => Auth::user()?->can('viewAny', Review::class) ?? false)
            ->requiresConfirmation()
            ->action(function (Review $record) use ($status): void {
                ModerateReview::run($record, $status);

                Notification::make()->title("Review {$status->value}")->success()->send();
            });
    }
}
