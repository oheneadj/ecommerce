<?php

/**
 * Covers managing free-form attribute values (e.g. Size, Color) on a
 * product variant from the Filament admin panel.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

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

class ProductVariantAttributesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_creating_a_variant_with_a_single_attribute_saves_it(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->mountTableAction('create')
            ->set('mountedActions.0.data.sku', 'SHIRT-M')
            ->set('mountedActions.0.data.price', 3000)
            ->set('mountedActions.0.data.stock', 10)
            ->set('mountedActions.0.data.status', 'active')
            ->set('mountedActions.0.data.attributeValues', [
                ['attribute_name' => 'Size', 'value' => 'Medium'],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $variant = ProductVariant::query()->where('sku', 'SHIRT-M')->sole();
        $attribute = $variant->attributeValues()->sole();
        $this->assertSame('Size', $attribute->attribute_name);
        $this->assertSame('Medium', $attribute->value);
    }

    public function test_a_variant_can_have_multiple_different_attributes_at_once(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->mountTableAction('create')
            ->set('mountedActions.0.data.sku', 'SHIRT-M-RED')
            ->set('mountedActions.0.data.price', 3000)
            ->set('mountedActions.0.data.stock', 10)
            ->set('mountedActions.0.data.status', 'active')
            ->set('mountedActions.0.data.attributeValues', [
                ['attribute_name' => 'Size', 'value' => 'Medium'],
                ['attribute_name' => 'Color', 'value' => 'Red'],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $variant = ProductVariant::query()->where('sku', 'SHIRT-M-RED')->sole();
        $this->assertSame(2, $variant->attributeValues()->count());
        $this->assertSame(
            ['Size' => 'Medium', 'Color' => 'Red'],
            $variant->attributeValues()->pluck('value', 'attribute_name')->all(),
        );
    }

    public function test_editing_a_variant_can_add_another_attribute(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->attributeValues()->create(['attribute_name' => 'Size', 'value' => 'Large']);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->mountTableAction('edit', $variant)
            ->set('mountedActions.0.data.attributeValues', [
                ['attribute_name' => 'Size', 'value' => 'Large'],
                ['attribute_name' => 'Color', 'value' => 'Blue'],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, $variant->attributeValues()->count());
        $this->assertSame(
            ['Size' => 'Large', 'Color' => 'Blue'],
            $variant->attributeValues()->pluck('value', 'attribute_name')->all(),
        );
    }
}
