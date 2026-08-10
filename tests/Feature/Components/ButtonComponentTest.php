<?php

/**
 * Covers the shared <x-button> component's "primary" variant, which every
 * storefront call-to-action (Add to cart, Checkout, etc.) uses — it must
 * track the store's brand colors (Epic E13.2) rather than a fixed color.
 */

declare(strict_types=1);

namespace Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ButtonComponentTest extends TestCase
{
    public function test_the_primary_variant_uses_the_brand_color_classes(): void
    {
        $html = Blade::render('<x-button variant="primary">Add to cart</x-button>');

        $this->assertStringContainsString('bg-brand-primary', $html);
        $this->assertStringContainsString('hover:bg-brand-secondary', $html);
    }
}
