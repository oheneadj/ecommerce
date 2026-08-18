<?php

/**
 * Resolves a stable identifier for the current visitor, for announcement view/dismiss tracking.
 */

declare(strict_types=1);

namespace App\Support;

use App\Actions\Cart\ResolveCurrentCart;
use Illuminate\Support\Facades\Auth;

/**
 * A logged-in customer is keyed by their own id; a guest reuses the exact
 * same session-id convention `ResolveCurrentCart::guestSessionId()` already
 * uses for guest carts, so reach/dismiss tracking works identically for
 * both without a nullable user_id column on `announcement_views`.
 */
class AnnouncementViewerKey
{
    public static function current(): string
    {
        return Auth::check()
            ? 'user_'.Auth::id()
            : 'guest_'.ResolveCurrentCart::guestSessionId();
    }
}
