<?php

/**
 * Resolves the current visitor's cart, whether they're logged in or a guest.
 */

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A guest's cart is tracked by their session ID, the same convention BRD
 * FR-3.3 describes ("session-based" guest cart state). `guestSessionId()`
 * reads the session ID from the *incoming request cookie* rather than
 * `session()->getId()` directly — `Illuminate\Auth\SessionGuard::login()`
 * regenerates the session ID before firing the `Login` event, so by the
 * time `MergeGuestCartOnLogin` runs, `session()->getId()` already returns
 * the new post-login ID. The original incoming cookie value is untouched
 * by that regeneration for the remainder of the request, so it's the only
 * reliable way to find "the guest cart this same request/session had"
 * from inside a `Login` event listener.
 */
class ResolveCurrentCart
{
    use AsAction;

    public function handle(?User $user, string $guestSessionId): Cart
    {
        if ($user !== null) {
            return GetCurrentCart::run($user);
        }

        return Cart::query()
            ->where('session_id', $guestSessionId)
            ->whereNull('user_id')
            ->whereDoesntHave('order')
            ->latest('id')
            ->first()
            ?? Cart::query()->create(['session_id' => $guestSessionId]);
    }

    public static function guestSessionId(): string
    {
        $cookie = Request::cookie(config('session.cookie'));

        return is_string($cookie) ? $cookie : session()->getId();
    }
}
