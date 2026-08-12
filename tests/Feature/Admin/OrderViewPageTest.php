<?php

/**
 * Covers the order's own view page: it shows a link to the customer, and
 * the same status/shipment/invoice actions the table row offers.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderViewPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    public function test_the_view_page_links_to_the_customer(): void
    {
        $this->actingAs($this->admin());

        $customer = User::factory()->create(['name' => 'Jane Customer']);
        $order = Order::factory()->create(['user_id' => $customer->id]);

        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee('Jane Customer')
            ->assertSee(CustomerResource::getUrl('view', ['record' => $customer]), escape: false);
    }

    public function test_a_guest_order_shows_the_guest_email_instead_of_a_customer_link(): void
    {
        $this->actingAs($this->admin());

        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com']);

        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee('guest@example.com');
    }

    public function test_update_status_action_works_from_the_view_page(): void
    {
        $this->actingAs($this->admin());

        $order = Order::factory()->create(['status' => OrderStatus::Pending]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('updateStatus', data: ['status' => OrderStatus::Paid->value])
            ->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_assign_shipment_action_works_from_the_view_page(): void
    {
        $this->actingAs($this->admin());

        $order = Order::factory()->create();
        $method = ShippingMethod::factory()->create();

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('assignShipment', data: ['shipping_method_id' => $method->id, 'tracking_number' => 'TRACK1'])
            ->assertHasNoActionErrors();

        $this->assertSame('TRACK1', $order->fresh()->shipment->tracking_number);
    }

    /**
     * Regression: `invoice_path` being set on the order doesn't guarantee
     * the file is still actually on disk (storage lost/reset without the
     * DB following along, previously an uncaught Flysystem
     * UnableToRetrieveMetadata 500). GenerateOrderInvoice renders
     * exclusively from the order's own permanently-snapshotted data, so
     * the download action regenerates it on the fly instead of crashing.
     */
    public function test_downloading_an_invoice_whose_file_is_missing_regenerates_it_instead_of_crashing(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        $order = Order::factory()->create(['invoice_path' => 'invoices/ORD-MISSING.pdf']);
        Storage::disk('local')->assertMissing($order->invoice_path);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('downloadInvoice');

        Storage::disk('local')->assertExists($order->fresh()->invoice_path);
    }

    public function test_downloading_an_existing_invoice_does_not_regenerate_it(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin());

        $order = Order::factory()->create();
        Storage::disk('local')->put('invoices/original.pdf', 'original-content');
        $order->update(['invoice_path' => 'invoices/original.pdf']);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->callAction('downloadInvoice');

        $this->assertSame('original-content', Storage::disk('local')->get('invoices/original.pdf'));
    }

    public function test_the_view_page_renders_shipping_details_from_the_address_snapshot(): void
    {
        $this->actingAs($this->admin());

        $order = Order::factory()->create([
            'address_id' => null,
            'address_snapshot' => [
                'recipient_name' => 'Snapshot Jane',
                'phone' => '+233209999999',
                'line1' => '12 Snapshot Street',
                'line2' => null,
                'city' => 'Accra',
                'region' => 'Greater Accra',
            ],
        ]);

        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk()
            ->assertSee('Snapshot Jane')
            ->assertSee('12 Snapshot Street');
    }
}
