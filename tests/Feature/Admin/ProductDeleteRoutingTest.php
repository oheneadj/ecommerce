<?php

/**
 * Covers that the admin panel's product delete buttons (single-record and
 * bulk) actually route through DeleteProduct — not Filament's plain
 * default delete, which would bypass the slug-mutation-for-reuse-safety
 * logic entirely.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductDeleteRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_deleting_a_product_via_its_edit_page_mutates_the_slug(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['slug' => 'my-product']);
        $id = $product->id;

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('delete');

        $this->assertSoftDeleted($product);
        $this->assertSame("my-product-deleted-{$id}", $product->fresh()->slug);
    }

    public function test_bulk_deleting_products_from_the_list_mutates_each_slug(): void
    {
        $this->actingAs($this->admin());

        $productA = Product::factory()->create(['slug' => 'product-a']);
        $productB = Product::factory()->create(['slug' => 'product-b']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('delete', [$productA, $productB]);

        $this->assertSoftDeleted($productA);
        $this->assertSoftDeleted($productB);
        $this->assertSame("product-a-deleted-{$productA->id}", $productA->fresh()->slug);
        $this->assertSame("product-b-deleted-{$productB->id}", $productB->fresh()->slug);
    }

    public function test_a_new_product_can_reuse_the_slug_of_one_deleted_via_the_admin_panel(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['slug' => 'reusable-slug']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('delete');

        $newProduct = Product::factory()->create(['slug' => 'reusable-slug']);

        $this->assertSame('reusable-slug', $newProduct->slug);
    }
}
