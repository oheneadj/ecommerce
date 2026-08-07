<?php

/**
 * Covers deleting a customer account that has order/address history — the
 * exact scenario that used to crash with a raw FK-constraint QueryException
 * (addresses.user_id cascades on delete, but orders.address_id blocked it).
 */

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\DeleteAccount;
use App\Actions\Checkout\CreateOrderFromCart;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_an_account_with_an_order_and_address_does_not_crash(): void
    {
        $user = User::factory()->create(['email' => 'shopper@example.com', 'phone' => '+233201234567']);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);

        $order = CreateOrderFromCart::run($cart, $address);

        DeleteAccount::run($user);

        $this->assertTrue($user->fresh()->trashed());
        // A soft delete is an UPDATE, not a DELETE — addresses.user_id's
        // cascadeOnDelete() never fires, so the address (and the order
        // pointing at it) is untouched. This is the fix: previously,
        // hard-deleting the user cascaded into the address, which then
        // hit orders.address_id's FK constraint and crashed.
        $this->assertNotNull(Address::query()->find($address->id));
        $this->assertNotNull($order->fresh());
        $this->assertSame($address->id, $order->fresh()->address_id);
    }

    public function test_deleting_an_address_directly_leaves_the_order_intact_via_its_snapshot(): void
    {
        // Covers orders.address_id's nullOnDelete() — if an address is
        // ever removed independently of a full account deletion (no such
        // feature exists yet, but the FK must already be correct for it),
        // the order must survive with its shipping details still readable
        // from the frozen snapshot, not the now-gone live Address.
        $address = Address::factory()->create(['recipient_name' => 'Jane Doe']);
        $cart = Cart::factory()->create();
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);
        $order = CreateOrderFromCart::run($cart, $address);

        $address->delete();

        $fresh = $order->fresh();
        $this->assertNull($fresh->address_id);
        $this->assertSame('Jane Doe', $fresh->address_snapshot['recipient_name']);
    }

    public function test_deleting_an_account_mutates_email_phone_and_google_id(): void
    {
        $user = User::factory()->create([
            'email' => 'shopper@example.com',
            'phone' => '+233201234567',
            'google_id' => 'google-123',
        ]);
        $id = $user->id;

        DeleteAccount::run($user);

        $trashed = User::withTrashed()->find($id);
        $this->assertSame("shopper@example.com-deleted-{$id}", $trashed->email);
        $this->assertSame("+233201234567-deleted-{$id}", $trashed->phone);
        $this->assertSame("google-123-deleted-{$id}", $trashed->google_id);
    }

    public function test_deleting_an_account_with_no_email_or_phone_does_not_error(): void
    {
        $user = User::factory()->create(['email' => null, 'phone' => null, 'google_id' => null]);

        DeleteAccount::run($user);

        $this->assertTrue($user->fresh()->trashed());
    }

    public function test_a_deleted_users_order_is_still_visible_and_correct_in_the_admin_panel(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id, 'recipient_name' => 'Jane Doe']);
        $cart = Cart::factory()->create(['user_id' => $user->id]);
        $variant = ProductVariant::factory()->create(['stock' => 10]);
        $cart->items()->create(['product_variant_id' => $variant->id, 'quantity' => 1]);
        $order = CreateOrderFromCart::run($cart, $address);

        DeleteAccount::run($user);

        $fresh = Order::query()->find($order->id);
        $this->assertSame('Jane Doe', $fresh->address_snapshot['recipient_name']);
    }
}
