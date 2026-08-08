<?php

/**
 * Covers the Products list page's header stats: total product count,
 * total stock units, and total inventory value (stock x price), scoped
 * to active variants only.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Enums\VariantStatus;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Widgets\ProductsOverviewWidget;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductsOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_it_shows_the_total_product_count(): void
    {
        $this->actingAs($this->admin());
        Product::factory()->count(3)->create();

        Livewire::test(ProductsOverviewWidget::class)
            ->assertSee('Total Products')
            ->assertSee('3');
    }

    public function test_it_sums_stock_and_value_across_active_variants_only(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::Active,
            'stock' => 10,
            'price' => 1000,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::Active,
            'stock' => 5,
            'price' => 2000,
        ]);
        // Inactive variant must not count toward either total.
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::Inactive,
            'stock' => 1000,
            'price' => 5000,
        ]);

        Livewire::test(ProductsOverviewWidget::class)
            ->assertSee('15 units')
            ->assertSee('GH₵200.00');
    }

    public function test_the_products_list_page_renders_with_the_overview_widget(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(ListProducts::class)->assertSuccessful();
    }

    public function test_it_has_exactly_three_stats_for_a_uniform_grid(): void
    {
        $this->actingAs($this->admin());

        $widget = new ProductsOverviewWidget;
        $stats = (new \ReflectionMethod($widget, 'getStats'))->invoke($widget);

        $this->assertCount(3, $stats);
    }
}
