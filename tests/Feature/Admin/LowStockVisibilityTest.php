<?php

/**
 * Covers actually seeing which products/variants are low on stock, not just
 * a bare count — the dashboard widget and the Products list's Low Stock tab.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Widgets\LowStockVariantsWidget;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LowStockVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_low_stock_widget_lists_the_affected_product_and_sku(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['name' => 'Low Stock Product']);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'LOW-SKU',
            'stock' => 2,
            'low_stock_threshold' => 5,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'HEALTHY-SKU',
            'stock' => 50,
            'low_stock_threshold' => 5,
        ]);

        Livewire::test(LowStockVariantsWidget::class)
            ->assertSee('Low Stock Product')
            ->assertSee('LOW-SKU')
            ->assertDontSee('HEALTHY-SKU');
    }

    public function test_products_low_stock_tab_only_includes_products_with_a_low_stock_variant(): void
    {
        $this->actingAs($this->admin());

        $lowStockProduct = Product::factory()->create(['name' => 'Low Stock Product']);
        ProductVariant::factory()->create([
            'product_id' => $lowStockProduct->id,
            'stock' => 1,
            'low_stock_threshold' => 5,
        ]);

        $healthyProduct = Product::factory()->create(['name' => 'Healthy Product']);
        ProductVariant::factory()->create([
            'product_id' => $healthyProduct->id,
            'stock' => 100,
            'low_stock_threshold' => 5,
        ]);

        $tabs = (new ListProducts)->getTabs();
        $lowStockQuery = $tabs['low_stock']->modifyQuery(Product::query());

        $this->assertTrue($lowStockQuery->pluck('id')->contains($lowStockProduct->id));
        $this->assertFalse($lowStockQuery->pluck('id')->contains($healthyProduct->id));
    }
}
