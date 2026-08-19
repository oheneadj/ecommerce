<?php

/**
 * Disables or re-enables a customer account.
 */

declare(strict_types=1);

namespace App\Actions\Customer;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Single entry point for both directions (row action and bulk action
 * alike). Disabling kills any active session immediately — flipping
 * `disabled_at` alone blocks future login attempts (VerifyOtp/
 * LoginWithGoogle/Fortify's authenticateUsing override already check it),
 * but wouldn't sign out someone already mid-session on the `database`
 * session driver. Re-enabling is a plain flag flip only: unlike staff
 * (SetStaffDisabledState), a customer has no invite flow to resend, and
 * many have no password at all (phone/Google-only accounts) — there's
 * nothing to reset.
 */
class SetCustomerDisabledState
{
    use AsAction;

    public function handle(User $customer, bool $disabled): void
    {
        $customer->update(['disabled_at' => $disabled ? now() : null]);

        if ($disabled) {
            DB::table('sessions')->where('user_id', $customer->id)->delete();
        }
    }
}
