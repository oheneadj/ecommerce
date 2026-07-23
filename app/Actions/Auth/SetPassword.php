<?php

/**
 * Lets an already-authenticated customer opt into email + password as an additional login method.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Email + password is never required at registration — a customer only gets
 * one by explicitly setting it up from account settings while already
 * authenticated via phone+OTP or Google (BRD FR-0.5).
 */
class SetPassword
{
    use AsAction;

    public function handle(User $user, string $password): void
    {
        $user->forceFill(['password' => $password])->save();
    }
}
