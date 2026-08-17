<?php

/**
 * Validates a phone number is in E.164 international format.
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Every phone number in this app is sent as-is to the SMS gateway with no
 * reformatting (App\Sms\Drivers\MoolreSms/GiantSms pass `$to` straight
 * through), so every number stored anywhere must already be in E.164 form:
 * a leading `+`, a non-zero country code digit, then 6-13 more digits
 * (8-15 digits total after the `+`, per the E.164 spec). Presence is left
 * to a separate `required` rule — an empty value is treated as valid here
 * so this composes cleanly with optional phone fields (e.g. a store's
 * display-only contact number).
 */
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (! preg_match('/^\+[1-9]\d{7,14}$/', $value)) {
            $fail('The :attribute must be a valid phone number in international format, e.g. +233201234567.');
        }
    }
}
