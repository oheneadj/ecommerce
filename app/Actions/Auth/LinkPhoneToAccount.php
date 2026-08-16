<?php

/**
 * Attaches a phone number to an already-authenticated account after OTP verification.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\InvalidOtpException;
use App\Exceptions\PhoneAlreadyLinkedException;
use App\Exceptions\TooManyOtpVerificationAttemptsException;
use App\Models\User;
use Illuminate\Database\QueryException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lets an email+password customer add a verified phone number to their
 * existing account (e.g. to also use phone+OTP login, or receive SMS
 * notifications) without creating a second account the way a fresh phone
 * login would. Per CLAUDE.md's identifier-linking rule, this is only safe
 * because the phone is independently verified via OTP here — never a
 * silent link off an unverified/coincidental match.
 */
class LinkPhoneToAccount
{
    use AsAction;

    /**
     * @throws InvalidOtpException when no usable code exists, it doesn't match, or it's locked out
     * @throws TooManyOtpVerificationAttemptsException when the phone has attempted verification too many times recently
     * @throws PhoneAlreadyLinkedException when the phone number already belongs to a different account
     */
    public function handle(User $user, string $phone, string $code): void
    {
        if (User::query()->where('phone', $phone)->whereKeyNot($user->id)->exists()) {
            throw new PhoneAlreadyLinkedException;
        }

        // Deliberately outside the try/transaction below: a failed attempt
        // (wrong code) must still persist its incremented attempts count —
        // wrapping it in the same transaction as the user save would roll
        // that increment back the moment ConsumeOtpCode throws, silently
        // defeating the per-code attempt lockout.
        ConsumeOtpCode::run($phone, $code, 'link_phone');

        try {
            $user->forceFill(['phone' => $phone, 'phone_verified_at' => now()])->save();
        } catch (QueryException $e) {
            // The pre-check above closes the common case, but a second
            // request for the same phone number could still slip in
            // between that check and this save — the column's own unique
            // constraint is the actual last line of defense.
            if (str_contains($e->getMessage(), 'users_phone_unique')) {
                throw new PhoneAlreadyLinkedException;
            }

            throw $e;
        }
    }
}
