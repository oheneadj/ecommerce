<?php

/**
 * Covers the Customers resource (backed by User, scoped to non-staff
 * accounts) — list, view, its Store-Keeper-excluded policy, and account
 * disable/enable (a customer's own data — name/email/phone — stays
 * read-only from here; disabling is a distinct account-state action).
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\AddressesRelationManager;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_viewing_a_customer_shows_every_address_theyve_saved(): void
    {
        $this->actingAs($this->admin());

        $customer = User::factory()->create();
        $home = Address::factory()->create(['user_id' => $customer->id, 'label' => 'Home']);
        $work = Address::factory()->create(['user_id' => $customer->id, 'label' => 'Work']);
        $someoneElses = Address::factory()->create();

        Livewire::test(AddressesRelationManager::class, ['ownerRecord' => $customer, 'pageClass' => ViewCustomer::class])
            ->assertCanSeeTableRecords([$home, $work])
            ->assertCanNotSeeTableRecords([$someoneElses]);
    }

    public function test_customers_can_be_bulk_exported(): void
    {
        $this->actingAs($this->admin());
        User::factory()->count(2)->create();

        Livewire::test(ListCustomers::class)
            ->assertTableBulkActionExists('export');
    }

    public function test_an_admin_can_disable_a_customer_account(): void
    {
        $this->actingAs($this->admin());
        $customer = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'session-1', 'user_id' => $customer->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        Livewire::test(ListCustomers::class)
            ->callTableAction('disable', $customer)
            ->assertHasNoTableActionErrors();

        $this->assertNotNull($customer->fresh()->disabled_at);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-1']);
    }

    public function test_an_admin_can_enable_a_previously_disabled_customer_account(): void
    {
        $this->actingAs($this->admin());
        $customer = User::factory()->create(['disabled_at' => now()]);

        Livewire::test(ListCustomers::class)
            ->callTableAction('enable', $customer)
            ->assertHasNoTableActionErrors();

        $this->assertNull($customer->fresh()->disabled_at);
    }

    public function test_the_disable_action_is_hidden_for_an_already_disabled_customer(): void
    {
        $this->actingAs($this->admin());
        $customer = User::factory()->create(['disabled_at' => now()]);

        Livewire::test(ListCustomers::class)
            ->assertTableActionHidden('disable', $customer)
            ->assertTableActionVisible('enable', $customer);
    }

    public function test_a_store_keeper_cannot_disable_a_customer(): void
    {
        $this->actingAs($this->storeKeeper());
        $customer = User::factory()->create();

        $this->assertFalse($this->storeKeeper()->can('setDisabledState', $customer));
    }

    public function test_disabled_customers_can_be_bulk_enabled(): void
    {
        $this->actingAs($this->admin());
        $first = User::factory()->create(['disabled_at' => now()]);
        $second = User::factory()->create(['disabled_at' => now()]);

        Livewire::test(ListCustomers::class)
            ->callTableBulkAction('bulkEnable', [$first, $second])
            ->assertHasNoTableBulkActionErrors();

        $this->assertNull($first->fresh()->disabled_at);
        $this->assertNull($second->fresh()->disabled_at);
    }
}
