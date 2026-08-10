<?php

/**
 * Covers the header cart indicator — live item count and preview dropdown,
 * shared across every storefront page.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\GetCurrentCart;
use App\Livewire\Storefront\CartIndicator;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CartIndicatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_no_item_count(): void
    {
        Livewire::test(CartIndicator::class)
            ->assertSet('open', false)
            ->assertSet('itemCount', 0);
    }

    public function test_the_indicator_shows_the_total_quantity_across_cart_items(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variantA = ProductVariant::factory()->create();
        $variantB = ProductVariant::factory()->create();
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variantA, 2);
        AddItemToCart::run($cart, $variantB, 3);

        Livewire::test(CartIndicator::class)->assertSee('5');
    }

    public function test_toggling_open_shows_the_cart_preview(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create(['price' => 1500, 'stock' => 5]);
        $cart = GetCurrentCart::run($user);
        AddItemToCart::run($cart, $variant, 2);

        Livewire::test(CartIndicator::class)
            ->call('toggle')
            ->assertSee('GH₵30.00')
            ->assertSee('View cart');
    }

    public function test_the_indicator_refreshes_when_a_cart_updated_event_is_dispatched(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = ProductVariant::factory()->create();
        $cart = GetCurrentCart::run($user);

        $component = Livewire::test(CartIndicator::class);
        AddItemToCart::run($cart, $variant, 1);

        $component->dispatch('cart-updated')->assertSee('1');
    }
}
