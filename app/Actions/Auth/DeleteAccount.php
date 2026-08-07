<?php

/**
 * Deletes a customer's own account, freeing their email/phone/google_id for reuse.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A soft delete — the row is preserved so past orders/reviews still
 * resolve a name, just hidden from login/customer-list queries via
 * Eloquent's default scope.
 *
 * `email`, `phone`, and `google_id` are all unique columns; mutating them
 * to `{original}-deleted-{id}` before the soft delete is the same "free
 * the unique value for reuse" rule already applied to Product/Review's
 * slug/order_item_id — without it, the customer could never register a new
 * account with the same email/phone again. The mutation uses the row's own
 * permanent `id`, so it's safe across repeated delete/re-register cycles.
 * Nothing about the old (now-inaccessible) row is ever exposed to or
 * merged with whatever new account later reuses the same email/phone —
 * they're entirely separate records.
 */
class DeleteAccount
{
    use AsAction;

    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->update([
                'email' => $user->email !== null ? "{$user->email}-deleted-{$user->id}" : null,
                'phone' => $user->phone !== null ? "{$user->phone}-deleted-{$user->id}" : null,
                'google_id' => $user->google_id !== null ? "{$user->google_id}-deleted-{$user->id}" : null,
            ]);

            $user->delete();
        });
    }
}
