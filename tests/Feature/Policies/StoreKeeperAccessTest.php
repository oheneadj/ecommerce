<?php

declare(strict_types=1);

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * BRD role table: Store Keeper's admin access is scoped to inventory and
 * product variants only — never orders, payments, or coupons.
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
}
