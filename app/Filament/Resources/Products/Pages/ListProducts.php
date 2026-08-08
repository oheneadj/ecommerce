<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Pages;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Widgets\ProductsOverviewWidget;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ProductsOverviewWidget::class,
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            ...collect(ProductStatus::cases())->mapWithKeys(fn (ProductStatus $status): array => [
                $status->value => Tab::make($status->label())
                    ->query(fn (Builder $query): Builder => $query->where('status', $status))
                    ->badge(Product::query()->where('status', $status)->count()),
            ])->all(),
            'low_stock' => Tab::make('Low Stock')
                ->query(fn (Builder $query): Builder => self::scopeToLowStock($query))
                ->badge(self::scopeToLowStock(Product::query())->count())
                ->badgeColor('warning'),
        ];
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    private static function scopeToLowStock(Builder $query): Builder
    {
        return $query->whereHas('variants', function (Builder $query): Builder {
            /** @var Builder<ProductVariant> $query */
            return $query->where('status', VariantStatus::Active)->lowStock();
        });
    }
}
