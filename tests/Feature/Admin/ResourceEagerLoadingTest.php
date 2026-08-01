<?php

/**
 * Covers that every Filament Resource whose table renders a relationship
 * column (e.g. `category.name`) actually eager-loads that relationship —
 * otherwise each row lazy-loads it individually (N+1) on every page render.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Filament\Resources\StockReservations\StockReservationResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string, array<int, string>}>
     */
    public static function resources(): array
    {
        return [
            'ActivityLogResource' => [ActivityLogResource::class, ['causer']],
            'CategoryResource' => [CategoryResource::class, ['parent']],
            'PaymentResource' => [PaymentResource::class, ['order']],
            'StockMovementResource' => [StockMovementResource::class, ['productVariant', 'user']],
            'StockReservationResource' => [StockReservationResource::class, ['productVariant']],
            'ReviewResource' => [ReviewResource::class, ['product', 'user']],
            'OrderResource' => [OrderResource::class, ['user', 'shipment']],
            'ProductResource' => [ProductResource::class, ['category', 'brand']],
        ];
    }

    public function test_resource_eager_loads_its_table_relationship_columns(): void
    {
        foreach (self::resources() as [$resource, $relations]) {
            $eagerLoads = array_keys($resource::getEloquentQuery()->getEagerLoads());

            foreach ($relations as $relation) {
                $this->assertContains(
                    $relation,
                    $eagerLoads,
                    "{$resource} does not eager-load '{$relation}', which its table displays — this causes an N+1 query per row.",
                );
            }
        }
    }
}
