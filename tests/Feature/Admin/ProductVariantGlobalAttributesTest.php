<?php

/**
 * Covers selecting global attributes/terms on a product and its variants
 * (instead of retyping free-text values) — the create form, the edit
 * page's product-level attribute toggle, and the Variants tab.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VariantStatus;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductVariantGlobalAttributesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_creating_a_product_enables_global_attributes_and_selects_variant_terms(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $large = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'Large']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Kente Shirt',
                'slug' => 'kente-shirt',
                'category_id' => $category->id,
                'status' => ProductStatus::Active->value,
                'attributes' => [$size->id],
                'variants' => [
                    [
                        'sku' => 'SHIRT-L',
                        'price' => 30,
                        'stock' => 10,
                        'status' => VariantStatus::Active->value,
                        'attribute_term_ids' => [$large->id],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', 'kente-shirt')->sole();
        $variant = $product->variants->sole();

        $this->assertTrue($product->attributes->contains($size));
        $this->assertSame(['Large'], $variant->attributeTerms->pluck('value')->all());
    }

    public function test_editing_a_product_can_change_its_enabled_attributes(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $product->attributes()->attach($size->id);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['attributes' => [$color->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['Color'], $product->fresh()->attributes->pluck('name')->all());
    }

    public function test_the_variants_tab_only_offers_terms_from_the_products_enabled_attributes(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $product->attributes()->attach($size->id);

        $large = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'Large']);
        $red = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Red']);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'sku' => 'SKU-1',
                'price' => 10,
                'stock' => 5,
                'status' => VariantStatus::Active->value,
                'attributeTerms' => [$large->id],
            ])
            ->assertHasNoTableActionErrors();

        $variant = $product->variants->sole();
        $this->assertSame(['Large'], $variant->attributeTerms->pluck('value')->all());
        $this->assertFalse($variant->attributeTerms->contains($red));
    }
}
