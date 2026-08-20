<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews\Tables;

use App\Actions\Review\ModerateReview;
use App\Enums\ReviewStatus;
use App\Models\Review;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Builds the admin table for moderating reviews — approve/reject actions
 * plus bulk equivalents, backed by ModerateReview.
 */
class ReviewsTable
{
    /**
     * Configures the reviews table's columns, filters, and actions.
     */
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
                    self::moderateBulkAction('approve', ReviewStatus::Approved),
                    self::moderateBulkAction('reject', ReviewStatus::Rejected),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->authorizeIndividualRecords('forceDelete'),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No reviews yet')
            ->emptyStateDescription('Reviews will appear here once customers start reviewing their purchases.')
            ->emptyStateIcon(Heroicon::OutlinedStar);
    }

    /**
     * Builds a single-record approve/reject row action for the given status.
     */
    private static function moderateAction(string $name, ReviewStatus $status): Action
    {
        return Action::make($name)
            ->label(ucfirst($name))
            ->button()
            ->visible(fn (Review $record) => $record->status !== $status)
            ->authorize(fn (): bool => Auth::user()?->can('viewAny', Review::class) ?? false)
            ->requiresConfirmation()
            ->action(function (Review $record) use ($status): void {
                ModerateReview::run($record, $status);

                Notification::make()->title("Review {$status->value}")->success()->send();
            });
    }

    /**
     * Bulk counterpart of moderateAction() above — same
     * ModerateReview::run() call, just looped over the selection rather
     * than a single record, so moderating a whole queue of pending
     * reviews doesn't mean clicking Approve/Reject one row at a time.
     */
    private static function moderateBulkAction(string $name, ReviewStatus $status): BulkAction
    {
        return BulkAction::make($name)
            ->label(ucfirst($name))
            ->authorize(fn (): bool => Auth::user()?->can('viewAny', Review::class) ?? false)
            ->requiresConfirmation()
            ->action(function (Collection $records) use ($status): void {
                foreach ($records as $record) {
                    if ($record instanceof Review) {
                        ModerateReview::run($record, $status);
                    }
                }

                Notification::make()->title("{$records->count()} review(s) {$status->value}")->success()->send();
            });
    }
}
