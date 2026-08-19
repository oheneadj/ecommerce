<?php

/**
 * Shared display formatting for money columns stored as integer minor units (pesewas).
 */

declare(strict_types=1);

namespace App\Concerns;

/**
 * Formats an integer minor-unit money column into a display string (e.g. 1550 -> "GH₵15.50"),
 * so no Blade view or Action ever does `/ 100` inline. Models using this trait should
 * expose one accessor per money column via `formattedMoney()`.
 */
trait HasFormattedMoney
{
    /**
     * Convert an integer minor-unit amount into a currency display string.
     */
    protected function formattedMoney(?int $minorUnits, ?string $currency = null): string
    {
        $currency ??= config('app.currency', 'GHS');
        $symbol = match ($currency) {
            'GHS' => 'GH₵',
            default => $currency.' ',
        };

        return $symbol.number_format(($minorUnits ?? 0) / 100, 2);
    }

    /**
     * Convert an integer minor-unit amount into a plain decimal string
     * with no currency symbol (e.g. 1550 -> "15.50") — for contexts that
     * need a machine-readable number rather than a display string, such
     * as a schema.org `price` value in JSON-LD.
     */
    protected function decimalMoney(?int $minorUnits): string
    {
        return number_format(($minorUnits ?? 0) / 100, 2, '.', '');
    }
}
