<?php

/**
 * Validates a phone number matches a recognized format and normalizes it to E.164.
 */

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Every phone number in this app is sent as-is to the SMS gateway with no
 * reformatting (App\Sms\Drivers\MoolreSms/GiantSms pass `$to` straight
 * through), so every number stored anywhere must end up in canonical E.164
 * form: a leading `+`, a non-zero country code digit, then more digits
 * (8-15 digits total after the `+`, per the E.164 spec).
 *
 * Customers don't type it that way, though — Ghanaian numbers are commonly
 * written as `0201234567` (local, trunk-prefix `0`), `233201234567` (country
 * code, no `+`), or `+233201234567` (full E.164). `normalize()` accepts all
 * three and returns the canonical E.164 form; this rule just reports
 * whether normalization is possible, it doesn't mutate the input itself —
 * callers that need the canonical value call `normalize()` directly (see
 * App\Livewire\Concerns\NormalizesPhoneNumber for the Livewire side, and
 * the `afterStateUpdated` live-normalization on the Filament phone fields).
 */
class PhoneNumber implements ValidationRule
{
    private const GHANA_COUNTRY_CODE = '233';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (self::normalize($value) === null) {
            $fail('The :attribute must be a valid phone number, e.g. +233201234567 or 0201234567.');
        }
    }

    /**
     * Normalizes any of the formats a customer might actually type into
     * canonical E.164. Returns null if the value doesn't match any
     * recognized shape. The bare-local-number case (`0...`) is assumed to
     * be Ghanaian — a reasonable default for this single-market storefront,
     * but not something to lean on if this app ever serves other countries.
     */
    public static function normalize(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^\+[1-9]\d{7,14}$/', $value)) {
            return $value;
        }

        if (preg_match('/^[1-9]\d{7,14}$/', $value)) {
            return '+'.$value;
        }

        if (preg_match('/^0\d{9}$/', $value)) {
            return '+'.self::GHANA_COUNTRY_CODE.substr($value, 1);
        }

        return null;
    }
}
