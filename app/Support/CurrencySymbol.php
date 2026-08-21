<?php

/**
 * Maps a configured currency code to its display symbol.
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Single source of truth for currency-code-to-symbol mapping, shared by
 * `HasFormattedMoney` (customer-facing display), `MoneyInput` (admin form
 * fields), and any other surface that needs a bare symbol rather than a
 * fully formatted amount — extracted so none of them drift independently
 * if `config('app.currency')` is ever set to something other than the
 * default GHS.
 */
class CurrencySymbol
{
    public static function for(?string $currency = null): string
    {
        $currency ??= config('app.currency', 'GHS');

        return match ($currency) {
            'GHS' => 'GH₵',
            default => $currency.' ',
        };
    }
}
