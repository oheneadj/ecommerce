<?php

/**
 * Removes a line item from a cart.
 */

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Models\CartItem;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveItemFromCart
{
    use AsAction;

    public function handle(CartItem $item): void
    {
        $item->delete();
    }
}
