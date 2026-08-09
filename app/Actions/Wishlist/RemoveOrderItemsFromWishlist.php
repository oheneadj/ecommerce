<?php

/**
 * Removes an order's purchased variants from the buyer's wishlist.
 */

declare(strict_types=1);

namespace App\Actions\Wishlist;

use App\Models\Order;
use App\Models\WishlistItem;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Called only once a payment has actually succeeded (SettlePaymentSuccess,
 * HandleLatePaymentConfirmation::fulfill) — never at order creation, since
 * a Pending order may still be abandoned or fail payment. Guest orders
 * have no `user_id` and therefore no wishlist to clean up.
 */
class RemoveOrderItemsFromWishlist
{
    use AsAction;

    public function handle(Order $order): void
    {
        if ($order->user_id === null) {
            return;
        }

        WishlistItem::query()
            ->where('user_id', $order->user_id)
            ->whereIn('product_variant_id', $order->items->pluck('product_variant_id'))
            ->delete();
    }
}
