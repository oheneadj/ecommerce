<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Catalog\AdjustVariantPrice;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_bulk_updating_order_status_updates_every_selected_order(): void
    {
        $this->actingAs($this->admin());

        $orders = Order::factory()->count(3)->create(['status' => OrderStatus::Pending]);

        Livewire::test(ListOrders::class)
            ->callTableBulkAction('bulkUpdateStatus', $orders, data: ['status' => OrderStatus::Paid->value])
            ->assertHasNoTableBulkActionErrors();

        foreach ($orders as $order) {
            $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        }
    }

    public function test_bulk_adjusting_stock_applies_the_delta_to_every_selected_variant(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variants = ProductVariant::factory()->count(2)->create(['product_id' => $product->id, 'stock' => 10]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableBulkAction('bulkAdjustStock', $variants, data: ['delta' => 5, 'note' => 'Restock'])
            ->assertHasNoTableBulkActionErrors();

        foreach ($variants as $variant) {
            $this->assertSame(15, $variant->fresh()->stock);
        }
    }

    public function test_adjusting_a_single_variants_stock_applies_the_delta_and_logs_a_movement(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 10]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('adjustStock', $variant, data: ['delta' => 5, 'note' => 'Restock'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(15, $variant->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_id' => $variant->id,
            'quantity' => 5,
        ]);
    }

    /**
     * Regression: the variant Edit form used to have a plain, directly
     * editable `stock` field — saving it did a raw column update, bypassing
     * RecordStockMovement/AdjustStockWithReservationCheck entirely (no
     * ledger entry, no reservation-safety check). `stock` is now
     * create-only; editing an existing variant must never change it, no
     * matter what's submitted in that field's place.
     */
    public function test_editing_a_variant_does_not_change_its_stock_even_if_submitted(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 10, 'sku' => 'SKU-1']);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('edit', $variant, data: [
                'sku' => 'SKU-1',
                'price' => $variant->price,
                'stock' => 999,
                'status' => $variant->status->value,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame(10, $variant->fresh()->stock);
        $this->assertSame(0, $variant->stockMovements()->count());
    }

    public function test_bulk_adjusting_price_applies_the_percentage_to_every_selected_variant(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variants = ProductVariant::factory()->count(2)->create(['product_id' => $product->id, 'price' => 1000]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableBulkAction('bulkAdjustPrice', $variants, data: ['percentage' => 10])
            ->assertHasNoTableBulkActionErrors();

        foreach ($variants as $variant) {
            $this->assertSame(1100, $variant->fresh()->price);
        }
    }

    public function test_a_failure_partway_through_a_bulk_price_adjustment_rolls_back_the_whole_batch(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variants = ProductVariant::factory()->count(2)->create(['product_id' => $product->id, 'price' => 1000]);

        $exceptionThrown = false;

        try {
            DB::transaction(function () use ($variants): void {
                AdjustVariantPrice::run($variants[0], 10);

                throw new RuntimeException('simulated failure partway through the batch');
            });
        } catch (RuntimeException) {
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown);
        $this->assertSame(1000, $variants[0]->fresh()->price);
    }
}
