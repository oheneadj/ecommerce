<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * BRD role table: Store Keeper owns day-to-day catalog work end-to-end —
 * creating and editing products, variants, and stock — but never orders,
 * payments, coupons, staff management, or deleting a product.
 */
class StoreKeeperAccessTest extends TestCase
{
    use RefreshDatabase;

    private function storeKeeper(): User
    {
        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');

        $user = User::factory()->create();
        $user->assignRole(UserRole::StoreKeeper->value);

        return $user;
    }

    public function test_store_keeper_cannot_view_orders_list(): void
    {
        $this->actingAs($this->storeKeeper())
            ->get('/admin/orders')
            ->assertForbidden();
    }

    public function test_store_keeper_cannot_view_a_single_order(): void
    {
        $order = Order::factory()->create();

        $this->assertFalse($this->storeKeeper()->can('view', $order));
    }

    public function test_store_keeper_cannot_view_payments_list(): void
    {
        $this->actingAs($this->storeKeeper())
            ->get('/admin/payments')
            ->assertForbidden();
    }

    public function test_store_keeper_cannot_view_a_single_payment(): void
    {
        $payment = Payment::factory()->create();

        $this->assertFalse($this->storeKeeper()->can('view', $payment));
    }

    public function test_store_keeper_cannot_view_coupons_list(): void
    {
        $this->actingAs($this->storeKeeper())
            ->get('/admin/coupons')
            ->assertForbidden();
    }

    public function test_store_keeper_cannot_view_a_single_coupon(): void
    {
        $coupon = Coupon::factory()->create();

        $this->assertFalse($this->storeKeeper()->can('view', $coupon));
    }

    public function test_store_keeper_can_view_stock_movements_list(): void
    {
        $this->actingAs($this->storeKeeper())
            ->get('/admin/stock-movements')
            ->assertSuccessful();
    }

    /**
     * Deliberate, not an oversight: a small store's owner delegating
     * day-to-day catalog data entry to a Store Keeper is a normal
     * operating model this app supports — requiring an Admin/SuperAdmin
     * to personally create every product would be real operational
     * overhead for no security benefit, since a product's own
     * name/description/price/category carry no access to money movement
     * (orders/payments/coupons stay fully out of reach regardless).
     */
    public function test_store_keeper_can_create_a_product(): void
    {
        $this->assertTrue($this->storeKeeper()->can('create', Product::class));
    }

    public function test_store_keeper_can_edit_a_products_core_fields(): void
    {
        $product = Product::factory()->create();

        $this->assertTrue($this->storeKeeper()->can('update', $product));
    }

    public function test_store_keeper_cannot_delete_a_product(): void
    {
        $product = Product::factory()->create();

        $this->assertFalse($this->storeKeeper()->can('delete', $product));
    }
}
