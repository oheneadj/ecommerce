<?php

/**
 * Regression: creating a variant with nonzero initial stock previously
 * wrote straight to `product_variants.stock` with no corresponding
 * `stock_movements` row — silently violating the invariant the
 * StockCacheMatchesMovements health check (System Health, Tier 3) exists
 * to catch, for every single new variant created via either path.
 */

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Actions\Catalog\GenerateProductVariants;
use App\Enums\StockMovementType;
use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VariantCreationStockMovementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_creating_a_single_variant_with_stock_records_a_matching_movement(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'sku' => 'SKU-1',
                'price' => 1000,
                'stock' => 25,
                'status' => 'active',
            ])
            ->assertHasNoTableActionErrors();

        $variant = $product->variants()->sole();

        $this->assertSame(25, $variant->stock);
        $this->assertSame(25, StockMovement::where('product_variant_id', $variant->id)->sum('quantity'));
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'type' => StockMovementType::Restock->value,
            'quantity' => 25,
        ]);
    }

    public function test_creating_a_single_variant_with_zero_stock_records_no_movement(): void
    {
        $this->actingAs($this->admin());
        $product = Product::factory()->create();

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'sku' => 'SKU-1',
                'price' => 1000,
                'stock' => 0,
                'status' => 'active',
            ])
            ->assertHasNoTableActionErrors();

        $variant = $product->variants()->sole();

        $this->assertSame(0, $variant->stock);
        $this->assertSame(0, StockMovement::where('product_variant_id', $variant->id)->count());
    }

    public function test_bulk_generating_variants_records_a_movement_per_variant(): void
    {
        $product = Product::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $large = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'Large']);
        $small = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'Small']);
        $admin = $this->admin();

        $created = GenerateProductVariants::run(
            $product,
            [[$large->id, $small->id]],
            1000,
            10,
            'SKU',
            $admin,
        );

        $this->assertCount(2, $created);

        foreach ($created as $variant) {
            $this->assertSame(10, $variant->stock);
            $this->assertSame(10, StockMovement::where('product_variant_id', $variant->id)->sum('quantity'));
            $this->assertDatabaseHas('stock_movements', [
                'product_variant_id' => $variant->id,
                'type' => StockMovementType::Restock->value,
                'quantity' => 10,
                'user_id' => $admin->id,
            ]);
        }
    }

    public function test_bulk_generating_variants_with_zero_default_stock_records_no_movement(): void
    {
        $product = Product::factory()->create();
        $size = Attribute::factory()->create(['name' => 'Size']);
        $large = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => 'Large']);

        $created = GenerateProductVariants::run($product, [[$large->id]], 1000, 0, 'SKU');

        $variant = $created->sole();
        $this->assertSame(0, $variant->stock);
        $this->assertSame(0, StockMovement::where('product_variant_id', $variant->id)->count());
    }
}
