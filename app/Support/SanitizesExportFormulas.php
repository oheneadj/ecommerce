<?php

/**
 * Neutralizes CSV/Excel formula-injection payloads in exported free-text values.
 */

declare(strict_types=1);

namespace App\Support;

/**
 * A cell whose content begins with `=`, `+`, `-`, or `@` is interpreted as
 * a formula by Excel/Sheets when the exported file is opened — a customer
 * setting their display name to `=HYPERLINK("http://evil.example","x")`
 * would have that formula execute on whichever admin later exports and
 * opens the customer list. Neither `pxlrbt/filament-excel` nor the
 * underlying PhpSpreadsheet writer neutralizes this by default. Prefixing
 * a single quote is the standard mitigation (same approach Google Sheets/
 * GitHub apply) — it forces the cell to be treated as literal text while
 * leaving the visible value unchanged in every spreadsheet application.
 */
class SanitizesExportFormulas
{
    public static function sanitize(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@'], true) ? "'{$value}" : $value;
    }
}
