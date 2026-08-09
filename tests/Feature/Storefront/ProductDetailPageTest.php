<?php

/**
 * Covers the public product detail page (/products/{product}) — variant
 * selection, reactive price/stock, add to cart/wishlist, and reviews.
 */

declare(strict_types=1);

namespace Tests\Feature\Storefront;

use App\Enums\ProductStatus;
use App\Enums\ReviewStatus;
use App\Livewire\Storefront\ProductDetailPage;
use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
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
}
