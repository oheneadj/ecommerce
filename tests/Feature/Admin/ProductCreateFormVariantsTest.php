<?php

/**
 * Covers the product create form's variant repeater — low_stock_threshold
 * and attribute values (Size, Color, etc.) can now be set on each variant
 * at creation time, not just afterward via the Variants tab.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Enums\VariantStatus;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductCreateFormVariantsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_creating_a_product_with_a_variant_low_stock_threshold_and_attributes(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Kente Shirt',
                'slug' => 'kente-shirt',
                'category_id' => $category->id,
                'status' => ProductStatus::Active->value,
                'variants' => [
                    [
                        'sku' => 'SHIRT-M-RED',
                        'price' => 3000,
                        'stock' => 10,
                        'low_stock_threshold' => 4,
                        'status' => VariantStatus::Active->value,
                        'attributeValues' => [
                            ['attribute_name' => 'Size', 'value' => 'M'],
                            ['attribute_name' => 'Color', 'value' => 'Red'],
                        ],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', 'kente-shirt')->sole();
        $variant = $product->variants->sole();

        $this->assertSame(4, $variant->low_stock_threshold);
        $this->assertSame(
            ['Size' => 'M', 'Color' => 'Red'],
            $variant->attributeValues->pluck('value', 'attribute_name')->all(),
        );
    }
}
