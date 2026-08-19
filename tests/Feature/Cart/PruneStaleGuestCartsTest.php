<?php

/**
 * Covers App\Actions\Cart\PruneStaleGuestCarts — the daily cleanup for
 * abandoned guest carts, closing an unbounded-storage-growth gap (a
 * script hitting /cart or /checkout repeatedly with cookies stripped
 * between requests could otherwise create one empty cart row per hit,
 * forever).
 */

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Actions\Cart\PruneStaleGuestCarts;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneStaleGuestCartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_a_stale_guest_cart(): void
    {
        $cart = Cart::factory()->create(['user_id' => null, 'session_id' => 'stale-session']);
        $cart->forceFill(['updated_at' => now()->subDays(2)])->save();

        PruneStaleGuestCarts::run();

        $this->assertModelMissing($cart);
    }

    public function test_it_never_deletes_a_recently_active_guest_cart(): void
    {
        $cart = Cart::factory()->create(['user_id' => null, 'session_id' => 'fresh-session']);

        PruneStaleGuestCarts::run();

        $this->assertModelExists($cart);
    }

    public function test_it_never_deletes_an_authenticated_users_cart(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id, 'session_id' => null]);
        $cart->forceFill(['updated_at' => now()->subDays(2)])->save();

        PruneStaleGuestCarts::run();

        $this->assertModelExists($cart);
    }

    public function test_it_never_deletes_a_stale_guest_cart_already_tied_to_an_order(): void
    {
        $cart = Cart::factory()->create(['user_id' => null, 'session_id' => 'stale-but-ordered']);
        $cart->forceFill(['updated_at' => now()->subDays(2)])->save();
        Order::factory()->create(['cart_id' => $cart->id]);

        PruneStaleGuestCarts::run();

        $this->assertModelExists($cart);
    }

    public function test_it_returns_the_number_of_carts_deleted(): void
    {
        $stale = Cart::factory()->count(3)->create(['user_id' => null]);
        $stale->each(fn (Cart $cart) => $cart->forceFill(['updated_at' => now()->subDays(2)])->save());
        Cart::factory()->create(['user_id' => null]);

        $deleted = PruneStaleGuestCarts::run();

        $this->assertSame(3, $deleted);
    }
}
