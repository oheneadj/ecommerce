<?php

/**
 * Covers the read-only Customers resource (backed by User, scoped to
 * non-staff accounts) — list, view, and its Store-Keeper-excluded policy.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomerResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function storeKeeper(): User
    {
        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::StoreKeeper->value);

        return $user;
    }

    public function test_customer_list_excludes_staff_accounts(): void
    {
        $this->actingAs($this->admin());

        $customer = User::factory()->create(['name' => 'Jane Customer']);
        $staff = User::factory()->create(['name' => 'Staff Member']);
        $staff->assignRole(UserRole::Admin->value);

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$customer])
            ->assertCanNotSeeTableRecords([$staff]);
    }

    public function test_store_keeper_cannot_access_the_customers_list(): void
    {
        $this->actingAs($this->storeKeeper());

        $this->get(CustomerResource::getUrl('index'))->assertForbidden();
    }

    public function test_viewing_a_customer_shows_their_order_history(): void
    {
        $this->actingAs($this->admin());

        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id, 'order_number' => 'ORD-2026-000999']);

        $this->get(CustomerResource::getUrl('view', ['record' => $customer]))
            ->assertOk()
            ->assertSee($customer->name);

        Livewire::test(OrdersRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
            ->assertCanSeeTableRecords([$order]);
    }
}
