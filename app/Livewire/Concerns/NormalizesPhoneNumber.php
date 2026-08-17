<?php

/**
 * Normalizes a Livewire component's phone-number property in place to canonical E.164 format.
 */

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Rules\PhoneNumber;

/**
 * Runs before any other validation (e.g. a uniqueness check) so that
 * validation always compares against the same canonical shape a stored
 * number would already be in — not whichever of the formats a customer
 * happened to type (see App\Rules\PhoneNumber::normalize()).
 */
trait NormalizesPhoneNumber
{
    /**
     * @param  string  $property  the component's public phone property to normalize in place
     * @param  string  $errorField  the error-bag key to attach a failure message to
     */
    protected function normalizePhoneOrFail(string $property, string $errorField): bool
    {
        $normalized = PhoneNumber::normalize($this->{$property});

        if ($normalized === null) {
            $this->addError($errorField, 'Please enter a valid phone number, e.g. +233201234567 or 0201234567.');

            return false;
        }

        $this->{$property} = $normalized;

        return true;
    }
}
