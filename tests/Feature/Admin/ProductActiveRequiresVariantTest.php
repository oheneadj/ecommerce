<?php

/**
 * Covers rejecting a product status change to Active when it has no
 * variants, and auto-downgrading a product to Draft when its last
 * variant is deleted — both from the admin panel.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductActiveRequiresVariantTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_setting_a_variant_less_product_to_active_via_the_edit_page_is_rejected(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['status' => ProductStatus::Draft]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['status' => ProductStatus::Active->value])
            ->call('save');

        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
    }

    public function test_setting_a_product_with_a_variant_to_active_via_the_edit_page_succeeds(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['status' => ProductStatus::Draft]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['status' => ProductStatus::Active->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(ProductStatus::Active, $product->fresh()->status);
    }

    public function test_deleting_the_last_variant_via_the_variants_tab_downgrades_the_product_to_draft(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('delete', $variant)
            ->assertHasNoTableActionErrors();

        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
    }

    public function test_bulk_deleting_the_last_variants_via_the_variants_tab_downgrades_the_product_to_draft(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variants = ProductVariant::factory()->count(2)->create(['product_id' => $product->id]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableBulkAction('delete', $variants)
            ->assertHasNoTableBulkActionErrors();

        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
        $this->assertSame(0, $product->variants()->count());
    }
}
