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

    public function test_creating_a_draft_product_with_no_variants_succeeds(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Work In Progress',
                'slug' => 'work-in-progress',
                'category_id' => $category->id,
                'status' => ProductStatus::Draft->value,
                'variants' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', 'work-in-progress')->sole();
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertCount(0, $product->variants);
    }

    /**
     * Regression test: Filament's Repeater defaults to pre-populating one
     * empty item unless told otherwise (`defaultItems(0)`), which — combined
     * with sku/price being required() inside it — silently forced every
     * fresh create form to already contain one unfillable blank variant row,
     * blocking submission even for a Draft product that shouldn't need one.
     */
    public function test_the_create_form_does_not_start_with_a_pre_filled_blank_variant_row(): void
    {
        $this->actingAs($this->admin());

        $this->assertSame([], Livewire::test(CreateProduct::class)->get('data.variants'));
    }

    public function test_submitting_the_create_form_untouched_succeeds_as_a_draft_with_no_variants(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Untouched Repeater',
                'slug' => 'untouched-repeater',
                'category_id' => $category->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', 'untouched-repeater')->sole();
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertCount(0, $product->variants);
    }

    public function test_creating_an_active_product_with_no_variants_is_rejected(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'No Variants',
                'slug' => 'no-variants',
                'category_id' => $category->id,
                'status' => ProductStatus::Active->value,
                'variants' => [],
            ])
            ->call('create');

        $this->assertSame(0, Product::query()->where('slug', 'no-variants')->count());
    }
}
