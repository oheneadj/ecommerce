<?php

/**
 * Covers CurrencySymbol — the single source of truth every money-display
 * surface (HasFormattedMoney, MoneyInput, the storefront price filter,
 * JSON-LD) reads its currency symbol from.
 */

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CurrencySymbol;
use Tests\TestCase;

class CurrencySymbolTest extends TestCase
{
    public function test_it_defaults_to_the_configured_currency(): void
    {
        config(['app.currency' => 'GHS']);

        $this->assertSame('GH₵', CurrencySymbol::for());
    }

    public function test_an_explicit_currency_overrides_the_config_default(): void
    {
        config(['app.currency' => 'GHS']);

        $this->assertSame('NGN ', CurrencySymbol::for('NGN'));
    }

    public function test_a_non_ghs_configured_currency_falls_back_to_a_generic_code_prefix(): void
    {
        config(['app.currency' => 'USD']);

        $this->assertSame('USD ', CurrencySymbol::for());
    }
}
