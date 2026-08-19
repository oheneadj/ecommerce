<?php

/**
 * Covers App\Support\SanitizesExportFormulas — neutralizing CSV/Excel
 * formula-injection payloads (a leading =, +, -, or @) in exported cells.
 */

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\SanitizesExportFormulas;
use PHPUnit\Framework\TestCase;

class SanitizesExportFormulasTest extends TestCase
{
    public function test_a_value_starting_with_equals_is_prefixed(): void
    {
        $this->assertSame("'=HYPERLINK(\"http://evil\",\"x\")", SanitizesExportFormulas::sanitize('=HYPERLINK("http://evil","x")'));
    }

    public function test_a_value_starting_with_plus_is_prefixed(): void
    {
        $this->assertSame("'+1+1", SanitizesExportFormulas::sanitize('+1+1'));
    }

    public function test_a_value_starting_with_minus_is_prefixed(): void
    {
        $this->assertSame("'-1+1", SanitizesExportFormulas::sanitize('-1+1'));
    }

    public function test_a_value_starting_with_at_is_prefixed(): void
    {
        $this->assertSame("'@SUM(1,2)", SanitizesExportFormulas::sanitize('@SUM(1,2)'));
    }

    public function test_an_ordinary_value_is_untouched(): void
    {
        $this->assertSame('Jane Doe', SanitizesExportFormulas::sanitize('Jane Doe'));
    }

    public function test_null_is_untouched(): void
    {
        $this->assertNull(SanitizesExportFormulas::sanitize(null));
    }

    public function test_an_empty_string_is_untouched(): void
    {
        $this->assertSame('', SanitizesExportFormulas::sanitize(''));
    }
}
