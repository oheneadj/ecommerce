<?php

/**
 * Covers the "View live" header action on the admin Edit Product page —
 * only meaningful (and only shown) once the product is actually reachable
 * on the storefront: Active status with at least one variant, since
 * `ProductDetailPage::mount()` 404s on anything else.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductViewLiveActionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_view_live_is_visible_for_an_active_product_with_a_variant(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionVisible('viewLive');
    }

    public function test_view_live_is_hidden_for_an_active_product_with_no_variants(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['status' => ProductStatus::Active]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionHidden('viewLive');
    }

    public function test_view_live_is_hidden_for_a_draft_product_with_a_variant(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['status' => ProductStatus::Draft]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertActionHidden('viewLive');
    }

    public function test_view_live_links_to_the_products_storefront_page(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSeeHtml(route('products.show', $product));
    }
}
