<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products;

use App\Enums\VariantStatus;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Filament\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use App\Models\ProductVariant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

/**
 * Filament resource for managing the product catalog — form, table,
 * variants/images relation managers, and CRUD pages.
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    /**
     * Builds the product create/edit form.
     */
    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    /**
     * Builds the product list table.
     */
    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    /**
     * Eager loads category and brand to avoid N+1s on the list table.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'brand']);
    }

    /**
     * Registers the Variants and Images relation managers.
     */
    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
            ImagesRelationManager::class,
        ];
    }

    /**
     * Registers the resource's index/create/edit pages.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    /**
     * Allows route model binding to resolve soft-deleted products (needed
     * for restore/force-delete actions on the edit page).
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Count of active variants at or below their low-stock threshold —
     * same definition DashboardMetricsQuery/LowStockVariantsWidget use.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = ProductVariant::query()
            ->where('status', VariantStatus::Active)
            ->lowStock()
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Colors the low-stock navigation badge as a warning.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
