<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews;

use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\Tables\ReviewsTable;
use App\Models\Review;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Moderation only — reviews are never created or content-edited via the
 * admin panel (only SubmitReview/EditReview, both customer-only, touch
 * content). The table exposes Approve/Reject/Delete actions calling
 * ModerateReview/DeleteReview.
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Reviews';

    /**
     * Configures the reviews list table.
     */
    public static function table(Table $table): Table
    {
        return ReviewsTable::configure($table);
    }

    /**
     * Eager loads the product and user relations for the list table.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['product', 'user']);
    }

    /**
     * No relation managers on this resource.
     */
    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    /**
     * Registers the pages available on this resource.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListReviews::route('/'),
        ];
    }

    /**
     * Includes soft-deleted reviews when resolving route model bindings, so
     * moderation actions can still target a deleted record.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Reviews are never created via the admin panel.
     */
    public static function canCreate(): bool
    {
        return false;
    }
}
