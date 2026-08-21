<?php

/**
 * Covers ShippingMethod's route-model-binding key — it previously had no
 * ULID/slug and exposed its raw bigint id in admin URLs, unlike every
 * other comparable resource.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\ShippingMethods\Pages\EditShippingMethod;
use App\Filament\Resources\ShippingMethods\Pages\ListShippingMethods;
use App\Models\Shipment;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ShippingMethodResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_shipping_method_does_not_expose_a_raw_bigint_id_via_route_key(): void
    {
        $shippingMethod = ShippingMethod::factory()->create();

        $this->assertSame('ulid', $shippingMethod->getRouteKeyName());
        $this->assertNotSame((string) $shippingMethod->id, $shippingMethod->getRouteKey());
    }

    /**
     * Regression: shipments.shipping_method_id is restrictOnDelete() at
     * the DB level — deleting a shipping method still referenced by a
     * shipment used to throw an unhandled QueryException (a raw 500)
     * instead of a clean, actionable message.
     */
    public function test_deleting_an_unused_shipping_method_succeeds(): void
    {
        $this->actingAs($this->admin());

        $shippingMethod = ShippingMethod::factory()->create();

        Livewire::test(EditShippingMethod::class, ['record' => $shippingMethod->getRouteKey()])
            ->callAction('delete');

        $this->assertModelMissing($shippingMethod);
    }

    public function test_deleting_a_shipping_method_used_by_a_shipment_is_blocked(): void
    {
        $this->actingAs($this->admin());

        $shippingMethod = ShippingMethod::factory()->create();
        Shipment::factory()->create(['shipping_method_id' => $shippingMethod->id]);

        Livewire::test(EditShippingMethod::class, ['record' => $shippingMethod->getRouteKey()])
            ->callAction('delete')
            ->assertNotified('Cannot delete shipping method');

        $this->assertModelExists($shippingMethod);
    }

    public function test_bulk_deleting_shipping_methods_is_blocked_while_any_selected_one_is_in_use(): void
    {
        $this->actingAs($this->admin());

        $unused = ShippingMethod::factory()->create();
        $inUse = ShippingMethod::factory()->create();
        Shipment::factory()->create(['shipping_method_id' => $inUse->id]);

        Livewire::test(ListShippingMethods::class)
            ->callTableBulkAction('delete', [$unused, $inUse])
            ->assertNotified('Cannot delete shipping methods');

        $this->assertModelExists($unused);
        $this->assertModelExists($inUse);
    }
}
