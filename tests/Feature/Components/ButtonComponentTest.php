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

    public function test_a_wire_click_button_automatically_disables_itself_while_loading(): void
    {
        $html = Blade::render('<x-button wire:click="save">Save</x-button>');

        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('wire:target="save"', $html);
    }

    public function test_a_submit_button_automatically_disables_itself_while_loading(): void
    {
        $html = Blade::render('<x-button type="submit">Save</x-button>');

        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
    }

    public function test_an_explicit_wire_target_is_not_overridden(): void
    {
        $html = Blade::render('<x-button wire:click="save" wire:target="somethingElse">Save</x-button>');

        $this->assertStringContainsString('wire:target="somethingElse"', $html);
        $this->assertStringNotContainsString('wire:target="save"', $html);
    }

    public function test_a_plain_link_button_gets_no_loading_wiring(): void
    {
        $html = Blade::render('<x-button href="/somewhere">Go</x-button>');

        $this->assertStringNotContainsString('wire:loading', $html);
    }
}
