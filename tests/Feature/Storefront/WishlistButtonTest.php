<?php

/**
 * Covers App\Livewire\Storefront\WishlistButton — the heart-icon toggle
 * embedded on product cards (both inside a Livewire-rendered page and the
 * plain HomeController-rendered homepage).
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Enums\VariantStatus;
use App\Livewire\Storefront\WishlistButton;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class WishlistButtonTest extends TestCase
{
    use RefreshDatabase;

    private function purchasableVariant(): ProductVariant
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => VariantStatus::Active,
            'stock' => 5,
        ]);
    }

    public function test_a_guest_clicking_the_button_is_redirected_to_login(): void
    {
        $variant = $this->purchasableVariant();

        Livewire::test(WishlistButton::class, ['variant' => $variant])
            ->call('toggle')
            ->assertRedirect(route('login.phone'));

        $this->assertSame(0, WishlistItem::query()->count());
    }

    public function test_an_authenticated_customer_can_add_a_variant_to_their_wishlist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = $this->purchasableVariant();

        Livewire::test(WishlistButton::class, ['variant' => $variant])
            ->assertSet('isWishlisted', false)
            ->call('toggle')
            ->assertSet('isWishlisted', true);

        $this->assertDatabaseHas('wishlist_items', ['user_id' => $user->id, 'product_variant_id' => $variant->id]);
    }

    public function test_clicking_again_removes_it_from_the_wishlist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $variant = $this->purchasableVariant();

        Livewire::test(WishlistButton::class, ['variant' => $variant])
            ->call('toggle')
            ->assertSet('isWishlisted', true)
            ->call('toggle')
            ->assertSet('isWishlisted', false);

        $this->assertSame(0, WishlistItem::query()->count());
    }

    public function test_the_button_appears_on_the_homepage_product_grid(): void
    {
        $product = Product::factory()->create(['name' => 'Wishlist Card Test', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Wishlist Card Test')
            ->assertSeeHtml('wire:click="toggle"');
    }

    public function test_the_button_appears_on_the_product_listing_grid(): void
    {
        $product = Product::factory()->create(['name' => 'Wishlist Listing Test', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'status' => VariantStatus::Active, 'stock' => 5]);

        $this->get('/products')
            ->assertOk()
            ->assertSee('Wishlist Listing Test')
            ->assertSeeHtml('wire:click="toggle"');
    }

    /**
     * Every product card on a listing page embeds its own WishlistButton
     * instance — without a cross-instance cache, each one ran its own
     * `wishlist_items` existence query (an N+1-shaped cost invisible to
     * `Model::preventLazyLoading()` since it's component composition, not
     * a relation traversal). Proves the query count for the wishlist
     * check doesn't scale with the number of cards on the page.
     */
    public function test_checking_wishlist_status_for_many_cards_issues_only_one_query(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $variants = ProductVariant::factory()->count(10)->create(['status' => VariantStatus::Active, 'stock' => 5]);

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        foreach ($variants as $variant) {
            Livewire::test(WishlistButton::class, ['variant' => $variant]);
        }

        $this->assertSame(1, $queryCount);
    }
}
