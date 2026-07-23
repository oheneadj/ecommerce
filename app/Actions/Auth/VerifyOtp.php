<?php

/**
 * Verifies a login OTP code and authenticates (or creates) the phone's user account.
 */

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\InvalidOtpException;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Registration and login are the same flow for phone+OTP: a new phone number
 * automatically creates an account on first successful verification, no
 * separate registration form required. Locks the code after 5 failed attempts.
 */
class VerifyOtp
{
    use AsAction;

    /**
     * @throws InvalidOtpException when no usable code exists, it doesn't match, or it's locked out
     */
    public function handle(string $phone, string $code): User
    {
        $otp = OtpCode::query()
            ->where('identifier', $phone)
            ->where('purpose', 'login')
            ->latest('id')
            ->first();

        if (! $otp || ! $otp->isUsable()) {
            throw new InvalidOtpException;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            throw new InvalidOtpException;
        }

        $otp->forceFill(['consumed_at' => now()])->save();

        $user = User::query()->firstOrCreate(['phone' => $phone]);

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);

        return $user;
    }
}
