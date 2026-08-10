<?php

/**
 * Covers folding a guest's session cart into their account cart on login
 * (BRD FR-3.2/FR-3.3) — MergeGuestCartOnLogin, registered against every
 * login path via the framework's Login event.
 */

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Actions\Cart\AddItemToCart;
use App\Listeners\MergeGuestCartOnLogin;
use App\Models\Cart;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Request;
use Tests\TestCase;

class GuestCartMergeOnLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_logging_in_reassigns_a_guest_cart_with_no_existing_user_cart(): void
    {
        $sessionId = 'guest-session-1';
        Request::instance()->cookies->set(config('session.cookie'), $sessionId);

        $variant = ProductVariant::factory()->create(['stock' => 5]);
        $guestCart = Cart::factory()->create(['user_id' => null, 'session_id' => $sessionId]);
        AddItemToCart::run($guestCart, $variant, 2);

        $user = User::factory()->create();

        (new MergeGuestCartOnLogin)->handle(new Login('web', $user, false));

        $mergedCart = $guestCart->fresh();
        $this->assertSame($user->id, $mergedCart->user_id);
        $this->assertNull($mergedCart->session_id);
        $this->assertSame(2, $mergedCart->items()->where('product_variant_id', $variant->id)->sole()->quantity);
    }

    public function test_logging_in_merges_a_guest_cart_into_an_existing_user_cart(): void
    {
        $sessionId = 'guest-session-2';
        Request::instance()->cookies->set(config('session.cookie'), $sessionId);

        $variant = ProductVariant::factory()->create(['stock' => 5]);
        $guestCart = Cart::factory()->create(['user_id' => null, 'session_id' => $sessionId]);
        AddItemToCart::run($guestCart, $variant, 2);

        $user = User::factory()->create();
        $userCart = Cart::factory()->create(['user_id' => $user->id]);
        AddItemToCart::run($userCart, $variant, 1);

        (new MergeGuestCartOnLogin)->handle(new Login('web', $user, false));

        $this->assertSame(0, Cart::query()->whereKey($guestCart->id)->count());
        $this->assertSame(3, $userCart->items()->where('product_variant_id', $variant->id)->sole()->quantity);
    }

    public function test_logging_in_with_no_guest_cart_does_nothing(): void
    {
        Request::instance()->cookies->set(config('session.cookie'), 'a-session-with-no-cart');

        $user = User::factory()->create();

        (new MergeGuestCartOnLogin)->handle(new Login('web', $user, false));

        $this->assertSame(0, Cart::query()->count());
    }
}
