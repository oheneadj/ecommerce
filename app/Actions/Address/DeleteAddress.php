<?php

/**
 * Removes a saved address from a customer's account.
 */

declare(strict_types=1);

namespace App\Actions\Address;

use App\Models\Address;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Safe even if the address was used on a past order — `orders.address_id`
 * nulls out (Order.address_snapshot already froze the shipping details
 * permanently, per AGENTS.md §13's historical-snapshot rule), so deleting
 * an address never touches order history.
 *
 * Deleting the current default promotes the next-most-recent remaining
 * address to default, so the account is never left with a saved address
 * book but no default to preselect at checkout.
 */
class DeleteAddress
{
    use AsAction;

    public function handle(Address $address): void
    {
        DB::transaction(function () use ($address): void {
            $wasDefault = $address->is_default;
            $userId = $address->user_id;

            $address->delete();

            if ($wasDefault && $userId !== null) {
                Address::query()
                    ->where('user_id', $userId)
                    ->latest('id')
                    ->first()
                    ?->update(['is_default' => true]);
            }
        });
    }
}
