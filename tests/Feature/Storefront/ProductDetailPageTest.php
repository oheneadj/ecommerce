<?php

/**
 * Covers the public product detail page (/products/{product}) — variant
 * selection, reactive price/stock, add to cart/wishlist, and reviews.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Actions\Cart\AddItemToCart;
use App\Actions\Cart\ResolveCurrentCart;
use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Livewire\Storefront\ProductDetailPage;
use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductDetailPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_view_a_published_products_detail_page(): void
    {
        $product = Product::factory()->create(['name' => 'Blue Sneakers', 'status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 5000]);

        $this->get("/products/{$product->slug}")
            ->assertOk()
            ->assertSee('Blue Sneakers')
            ->assertSee('GH₵50.00');
    }

    public function test_a_draft_product_is_a_404(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Draft]);

        $this->get("/products/{$product->slug}")->assertNotFound();
    }

    public function test_selecting_an_attribute_term_switches_to_the_matching_variant(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $attribute = Attribute::factory()->create(['name' => 'Size']);
        $product->attributes()->attach($attribute->id);
        $small = AttributeTerm::factory()->create(['attribute_id' => $attribute->id, 'value' => 'Small']);
        $large = AttributeTerm::factory()->create(['attribute_id' => $attribute->id, 'value' => 'Large']);

        $smallVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000]);
        $smallVariant->attributeTerms()->attach($small->id);
        $largeVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 2000]);
        $largeVariant->attributeTerms()->attach($large->id);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->assertSee('GH₵10.00')
            ->call('selectTerm', $attribute->id, $large->id)
            ->assertSee('GH₵20.00');
    }

    /**
     * Regression: a product with more than one variant but no global
     * Attribute attached (e.g. variants distinguished only by SKU/price —
     * the common case, per real catalog data) previously had no UI at all
     * for reaching any variant past the first. The fallback "Options" list
     * must let the customer switch to it directly.
     */
    public function test_a_product_with_no_attribute_selector_falls_back_to_a_direct_variant_list(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $small = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SHOE-S', 'price' => 1000]);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SHOE-L', 'price' => 2000]);

        $component = Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->assertSet('hasAttributeSelector', false)
            ->assertSet('selectedVariant.id', $small->id)
            ->assertSee('GH₵10.00')
            ->assertSee('SHOE-S')
            ->assertSee('SHOE-L');

        $component->call('selectVariant', $large->id)
            ->assertSet('selectedVariant.id', $large->id)
            ->assertSee('GH₵20.00');
    }

    /**
     * Regression: selecting a variant (via the fallback list) previously
     * lived only in Livewire component state — a reload always went back
     * to `variants->first()`, discarding the customer's choice. It must
     * round-trip through the URL (`?variant=id`).
     */
    public function test_the_selected_variant_survives_a_full_page_reload_via_the_url(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000]);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 2000]);

        $this->get("/products/{$product->slug}?variant={$large->id}")
            ->assertOk()
            ->assertSee('GH₵20.00')
            ->assertDontSee('GH₵10.00');
    }

    /**
     * Same regression, for the attribute-term-based selector.
     */
    public function test_the_selected_attribute_term_survives_a_full_page_reload_via_the_url(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $attribute = Attribute::factory()->create(['name' => 'Size']);
        $product->attributes()->attach($attribute->id);
        $small = AttributeTerm::factory()->create(['attribute_id' => $attribute->id, 'value' => 'Small']);
        $large = AttributeTerm::factory()->create(['attribute_id' => $attribute->id, 'value' => 'Large']);

        $smallVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000]);
        $smallVariant->attributeTerms()->attach($small->id);
        $largeVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 2000]);
        $largeVariant->attributeTerms()->attach($large->id);

        $this->get("/products/{$product->slug}?options[{$attribute->id}]={$large->id}")
            ->assertOk()
            ->assertSee('GH₵20.00')
            ->assertDontSee('GH₵10.00');
    }

    /**
     * A variant with no attribute term set at all, on a product that
     * otherwise uses the global attribute selector, is incomplete catalog
     * data — it can never be reached through the selector and must not
     * be selectable/shown, including as the implicit default variant.
     */
    public function test_a_variant_with_no_attribute_term_is_excluded_when_the_product_uses_the_attribute_selector(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $attribute = Attribute::factory()->create(['name' => 'Size']);
        $product->attributes()->attach($attribute->id);
        $small = AttributeTerm::factory()->create(['attribute_id' => $attribute->id, 'value' => 'Small']);

        $smallVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SHOE-S', 'price' => 1000]);
        $smallVariant->attributeTerms()->attach($small->id);
        $bareVariant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'SHOE-BARE', 'price' => 500]);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->assertSet('selectedVariant.id', $smallVariant->id)
            ->assertDontSee('SHOE-BARE');
    }

    /**
     * The original reported bug: with Color × Size variants, a size that
     * was never individually photographed used to fall back to the
     * product's generic image pool — which could easily belong to the
     * wrong color. Images scoped to the Color term (`attribute_term_id`)
     * are now shared by every size of that color instead.
     */
    public function test_a_variant_with_no_images_of_its_own_uses_its_color_terms_shared_images(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $size = Attribute::factory()->create(['name' => 'Size']);
        $product->attributes()->attach([$color->id, $size->id]);
        $green = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Green']);
        $white = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'White']);
        $size39 = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => '39']);
        $size40 = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => '40']);

        ProductImage::factory()->create(['product_id' => $product->id, 'attribute_term_id' => $green->id, 'path' => 'product-images/green.jpg']);
        ProductImage::factory()->create(['product_id' => $product->id, 'attribute_term_id' => $white->id, 'path' => 'product-images/white.jpg']);

        $green40 = ProductVariant::factory()->create(['product_id' => $product->id]);
        $green40->attributeTerms()->attach([$green->id, $size40->id]);
        $green39 = ProductVariant::factory()->create(['product_id' => $product->id]);
        $green39->attributeTerms()->attach([$green->id, $size39->id]);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->call('selectTerm', $color->id, $green->id)
            ->call('selectTerm', $size->id, $size39->id)
            ->assertSet('selectedVariant.id', $green39->id)
            ->assertSee('green.jpg');
    }

    /**
     * A variant with its own uploaded images must keep showing those,
     * even when a term-scoped image set also exists for its color — a
     * per-variant override always wins.
     */
    public function test_a_variants_own_images_take_priority_over_its_color_terms_shared_images(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $product->attributes()->attach($color->id);
        $green = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Green']);
        ProductImage::factory()->create(['product_id' => $product->id, 'attribute_term_id' => $green->id, 'path' => 'product-images/shared-green.jpg']);

        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variant->attributeTerms()->attach($green->id);
        ProductImage::factory()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'path' => 'product-images/own.jpg']);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->assertSee('own.jpg')
            ->assertDontSee('shared-green.jpg');
    }

    /**
     * The gallery's lightbox (opened by clicking the main image, with a
     * carousel across every image) must list every one of the product's
     * images as candidates, not just the one shown initially.
     */
    public function test_the_gallery_lists_every_image_as_a_lightbox_candidate(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);
        ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'product-images/one.jpg']);
        ProductImage::factory()->create(['product_id' => $product->id, 'path' => 'product-images/two.jpg']);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->assertSee('one.jpg')
            ->assertSee('two.jpg')
            ->assertSeeHtml('lightboxOpen');
    }

    /**
     * Regression: previously, selecting a term combination with no
     * matching variant silently fell back to `variants->first()` — the
     * cheapest-priced variant overall, regardless of color — misrepresenting
     * what was actually selected. It must show "unavailable" instead.
     */
    public function test_selecting_a_combination_with_no_matching_variant_shows_unavailable(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $size = Attribute::factory()->create(['name' => 'Size']);
        $product->attributes()->attach([$color->id, $size->id]);
        $green = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Green']);
        $size42 = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => '42']);
        $size40 = AttributeTerm::factory()->create(['attribute_id' => $size->id, 'value' => '40']);

        // Only Green-40 exists as an actual variant — Green-42 was never stocked.
        $green40 = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 1000]);
        $green40->attributeTerms()->attach([$green->id, $size40->id]);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->call('selectTerm', $color->id, $green->id)
            ->call('selectTerm', $size->id, $size42->id)
            ->assertSet('selectedVariant', null)
            ->assertSee('Currently unavailable');
    }

    public function test_the_stock_count_is_shown_for_the_selected_variant(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 7]);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->assertSee('7 in stock');
    }

    public function test_a_single_variant_product_shows_no_options_list(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->assertDontSee('Options');
    }

    public function test_an_authenticated_customer_can_add_the_selected_variant_to_their_cart(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 5]);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->call('addToCart');

        $this->assertSame(1, $user->orders()->count() + Cart::query()->where('user_id', $user->id)->sole()->items()->where('product_variant_id', $variant->id)->count());
    }

    public function test_a_guest_can_add_the_selected_variant_to_a_session_cart(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 5]);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->call('addToCart');

        $guestCart = Cart::query()->whereNull('user_id')->sole();
        $this->assertSame(1, $guestCart->items()->where('product_variant_id', $variant->id)->count());
    }

    public function test_adding_to_cart_dispatches_the_cart_item_added_event_so_the_mini_cart_can_auto_open(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 5]);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->call('addToCart')
            ->assertDispatched('cart-item-added');
    }

    public function test_adding_to_cart_when_already_at_stock_shows_an_error_toast_instead_of_overselling(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 1]);
        AddItemToCart::run(ResolveCurrentCart::run($user, ResolveCurrentCart::guestSessionId()), $variant, 1);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->call('addToCart')
            ->assertDispatched('toast', variant: 'error', message: 'Only 1 left in stock.');

        $this->assertSame(1, Cart::query()->where('user_id', $user->id)->sole()->items()->sole()->quantity);
    }

    public function test_only_approved_reviews_are_shown(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);
        Review::factory()->create(['product_id' => $product->id, 'user_id' => $user->id, 'status' => ReviewStatus::Approved, 'body' => 'Great product, approved review.']);
        Review::factory()->create(['product_id' => $product->id, 'user_id' => $user->id, 'status' => ReviewStatus::Pending, 'body' => 'This one is still pending review.']);

        $this->get("/products/{$product->slug}")
            ->assertSee('Great product, approved review.')
            ->assertDontSee('This one is still pending review.');
    }

    public function test_wishlist_button_reflects_state_and_toggles_on_click(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::actingAs($user)->test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->assertSet('isWishlisted', false)
            ->assertSeeText('Add to wishlist')
            ->call('toggleWishlist')
            ->assertSet('isWishlisted', true)
            ->assertSeeText('In wishlist')
            ->call('toggleWishlist')
            ->assertSet('isWishlisted', false)
            ->assertSeeText('Add to wishlist');

        $this->assertSame(
            0,
            WishlistItem::query()->where('user_id', $user->id)->where('product_variant_id', $variant->id)->count(),
        );
    }

    public function test_guest_is_redirected_to_login_when_attempting_to_wishlist(): void
    {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(ProductDetailPage::class, ['productSlug' => $product->slug])
            ->call('toggleWishlist')
            ->assertRedirect(route('login.phone'));
    }
}
