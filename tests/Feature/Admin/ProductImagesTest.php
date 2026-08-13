<?php

/**
 * Covers uploading product-level and variant-scoped images (including
 * multiple files in one submission) from the Filament admin panel, and
 * that removing an image cleans up its stored file.
 */

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Attribute;
use App\Models\AttributeTerm;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductImagesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate(UserRole::Admin->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::Admin->value);

        return $user;
    }

    private function storeKeeper(): User
    {
        Role::findOrCreate(UserRole::StoreKeeper->value, 'web');
        $user = User::factory()->create();
        $user->assignRole(UserRole::StoreKeeper->value);

        return $user;
    }

    public function test_uploading_a_general_product_image_leaves_the_variant_scope_blank(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'images' => [UploadedFile::fake()->image('front.jpg')],
            ])
            ->assertHasNoTableActionErrors();

        $image = $product->images()->sole();
        $this->assertNull($image->product_variant_id);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_uploading_multiple_general_product_images_at_once_creates_one_row_per_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'images' => [
                    UploadedFile::fake()->image('one.jpg'),
                    UploadedFile::fake()->image('two.jpg'),
                    UploadedFile::fake()->image('three.jpg'),
                ],
            ])
            ->assertHasNoTableActionErrors();

        $images = $product->images()->orderBy('sort_order')->get();

        $this->assertCount(3, $images);
        $this->assertTrue($images->every(fn (ProductImage $image) => $image->product_variant_id === null));
        $this->assertTrue($images->every(fn (ProductImage $image) => ! $image->is_primary));
        $this->assertSame([0, 1, 2], $images->pluck('sort_order')->all());
        foreach ($images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }

    public function test_multiple_general_images_continue_the_sort_order_of_existing_images(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 5]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'images' => [
                    UploadedFile::fake()->image('one.jpg'),
                    UploadedFile::fake()->image('two.jpg'),
                ],
            ])
            ->assertHasNoTableActionErrors();

        $newSortOrders = $product->images()->where('sort_order', '>', 5)->orderBy('sort_order')->pluck('sort_order');

        $this->assertSame([6, 7], $newSortOrders->all());
    }

    public function test_uploading_a_variant_scoped_image_records_the_variant(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'images' => [UploadedFile::fake()->image('red-variant.jpg')],
                'scope_type' => 'variant',
                'product_variant_id' => $variant->id,
            ])
            ->assertHasNoTableActionErrors();

        $image = $product->images()->sole();
        $this->assertSame($variant->id, $image->product_variant_id);
    }

    public function test_uploading_an_attribute_value_scoped_image_records_the_term_and_leaves_the_variant_scope_blank(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $color = Attribute::factory()->create(['name' => 'Color']);
        $product->attributes()->attach($color->id);
        $green = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Green']);
        ProductVariant::factory()->create(['product_id' => $product->id])->attributeTerms()->attach($green->id);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'images' => [UploadedFile::fake()->image('green.jpg')],
                'scope_type' => 'attribute_term',
                'attribute_term_id' => $green->id,
            ])
            ->assertHasNoTableActionErrors();

        $image = $product->images()->sole();
        $this->assertSame($green->id, $image->attribute_term_id);
        $this->assertNull($image->product_variant_id);
    }

    /**
     * Multiple files uploaded at once for an attribute-term scope (the
     * case that most motivated multi-upload — a color's photos, shared
     * across every size) all share the same scope.
     */
    public function test_uploading_multiple_attribute_value_scoped_images_all_share_the_same_term(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $color = Attribute::factory()->create(['name' => 'Color']);
        $product->attributes()->attach($color->id);
        $green = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Green']);
        ProductVariant::factory()->create(['product_id' => $product->id])->attributeTerms()->attach($green->id);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'images' => [
                    UploadedFile::fake()->image('green-front.jpg'),
                    UploadedFile::fake()->image('green-back.jpg'),
                ],
                'scope_type' => 'attribute_term',
                'attribute_term_id' => $green->id,
            ])
            ->assertHasNoTableActionErrors();

        $images = $product->images()->get();

        $this->assertCount(2, $images);
        $this->assertTrue($images->every(fn (ProductImage $image) => $image->attribute_term_id === $green->id));
        $this->assertTrue($images->every(fn (ProductImage $image) => $image->product_variant_id === null));
    }

    /**
     * Regression: submitting with a leftover `product_variant_id` from a
     * previous scope choice (e.g. switching from "variant" to "attribute
     * value" in the form) must not silently persist both scopes at once —
     * the inactive column is always cleared based on `scope_type`, for
     * every row created in a multi-file submission, not just the first.
     */
    public function test_switching_scope_to_attribute_value_clears_a_stale_variant_id(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $color = Attribute::factory()->create(['name' => 'Color']);
        $product->attributes()->attach($color->id);
        $green = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Green']);
        $variant->attributeTerms()->attach($green->id);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'images' => [
                    UploadedFile::fake()->image('green-1.jpg'),
                    UploadedFile::fake()->image('green-2.jpg'),
                ],
                'scope_type' => 'attribute_term',
                'attribute_term_id' => $green->id,
                'product_variant_id' => $variant->id,
            ])
            ->assertHasNoTableActionErrors();

        $images = $product->images()->get();

        $this->assertCount(2, $images);
        $this->assertTrue($images->every(fn (ProductImage $image) => $image->attribute_term_id === $green->id));
        $this->assertTrue($images->every(fn (ProductImage $image) => $image->product_variant_id === null));
    }

    /**
     * Regression: the "Attribute value" scope select used to list every
     * term the attached attribute has ever had globally (e.g. every color
     * in the catalog), not just the ones this product's own variants
     * actually carry. An attribute-value-scoped image only ever shows on a
     * variant carrying that exact term, so an unused term was never a
     * meaningful choice here — offering it was just confusing. Scoping a
     * new image to a term none of this product's variants actually use is
     * now rejected as an invalid option, the same way Filament rejects any
     * value outside a select's option list.
     */
    public function test_the_attribute_value_scope_rejects_a_term_this_products_variants_do_not_carry(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $color = Attribute::factory()->create(['name' => 'Color']);
        $product->attributes()->attach($color->id);
        $unusedGreen = AttributeTerm::factory()->create(['attribute_id' => $color->id, 'value' => 'Green']);
        // Some other variant uses this term, but not one belonging to this product.
        ProductVariant::factory()->create()->attributeTerms()->attach($unusedGreen->id);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'images' => [UploadedFile::fake()->image('green.jpg')],
                'scope_type' => 'attribute_term',
                'attribute_term_id' => $unusedGreen->id,
            ])
            ->assertHasTableActionErrors(['attribute_term_id']);

        $this->assertSame(0, $product->images()->count());
    }

    public function test_an_upload_over_the_configured_size_limit_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        config(['media.max_upload_size_kb' => 100]);

        $product = Product::factory()->create();

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'images' => [UploadedFile::fake()->image('too-big.jpg')->size(200)],
            ])
            // FileUpload::maxSize() registers a Closure rule rather than a
            // plain "max" string, so assert on the key alone.
            ->assertHasTableActionErrors(['images']);
    }

    public function test_deleting_an_image_removes_the_stored_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $path = 'product-images/existing.jpg';
        Storage::disk('public')->put($path, 'fake-image-contents');
        $image = ProductImage::factory()->create(['product_id' => $product->id, 'path' => $path]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('delete', $image)
            ->assertHasNoTableActionErrors();

        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_store_keeper_cannot_delete_a_product_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->storeKeeper());

        $product = Product::factory()->create();
        $image = ProductImage::factory()->create(['product_id' => $product->id]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->assertTableActionHidden('delete', $image);
    }

    /**
     * Editing a single existing image is unaffected by multi-upload being
     * enabled on create — the field stays a plain single-file upload
     * there, and sort_order/is_primary (hidden on create) are still
     * settable per row.
     */
    public function test_editing_a_single_image_still_works_and_can_set_it_primary(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $image = ProductImage::factory()->create(['product_id' => $product->id, 'is_primary' => false, 'sort_order' => 0]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('edit', $image, data: [
                'sort_order' => 3,
                'is_primary' => true,
            ])
            ->assertHasNoTableActionErrors();

        $image->refresh();
        $this->assertTrue($image->is_primary);
        $this->assertSame(3, $image->sort_order);
    }

    /**
     * Regression: the table only ever exposed `sort_order` as a manually-
     * typed number on the edit form — there was no actual drag-and-drop,
     * despite the column existing specifically to control gallery display
     * order. `->reorderable('sort_order')` wires up Filament's native
     * drag handles, tested here via the same `reorderTable` call the
     * generated JS drag interaction itself triggers.
     */
    public function test_dragging_to_reorder_persists_the_new_sort_order(): void
    {
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $first = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 0]);
        $second = ProductImage::factory()->create(['product_id' => $product->id, 'sort_order' => 1]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->call('reorderTable', [(string) $second->getKey(), (string) $first->getKey()]);

        $this->assertTrue($second->fresh()->sort_order < $first->fresh()->sort_order);
    }

    public function test_adding_an_image_from_a_variant_row_scopes_it_to_that_variant(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('addImage', $variant, data: [
                'images' => [UploadedFile::fake()->image('variant.jpg')],
            ])
            ->assertHasNoTableActionErrors();

        $image = $product->images()->sole();
        $this->assertSame($variant->id, $image->product_variant_id);
        $this->assertFalse($image->is_primary);
        Storage::disk('public')->assertExists($image->path);
    }

    public function test_adding_multiple_images_from_a_variant_row_creates_one_row_per_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('addImage', $variant, data: [
                'images' => [
                    UploadedFile::fake()->image('angle-1.jpg'),
                    UploadedFile::fake()->image('angle-2.jpg'),
                    UploadedFile::fake()->image('angle-3.jpg'),
                ],
            ])
            ->assertHasNoTableActionErrors();

        $images = $variant->images()->orderBy('sort_order')->get();

        $this->assertCount(3, $images);
        $this->assertTrue($images->every(fn (ProductImage $image) => $image->product_variant_id === $variant->id));
        $this->assertTrue($images->every(fn (ProductImage $image) => ! $image->is_primary));
        $this->assertSame([0, 1, 2], $images->pluck('sort_order')->all());
    }

    public function test_adding_images_from_a_variant_row_continues_the_variants_existing_sort_order(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        ProductImage::factory()->create(['product_id' => $product->id, 'product_variant_id' => $variant->id, 'sort_order' => 2]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('addImage', $variant, data: [
                'images' => [UploadedFile::fake()->image('angle-2.jpg')],
            ])
            ->assertHasNoTableActionErrors();

        $newest = $variant->images()->reorder('sort_order', 'desc')->first();

        $this->assertSame(3, $newest->sort_order);
    }

    public function test_store_keeper_can_also_add_an_image_from_a_variant_row(): void
    {
        Storage::fake('public');
        $this->actingAs($this->storeKeeper());

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('addImage', $variant, data: [
                'images' => [UploadedFile::fake()->image('variant.jpg')],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame($variant->id, $product->images()->sole()->product_variant_id);
    }

    /**
     * Neither the general-create nor the variant quick-add flow can
     * auto-mark an upload primary anymore (dropped in favor of always
     * setting it explicitly afterward via Edit) — creating new images
     * must never disturb whatever is already marked primary elsewhere.
     */
    public function test_creating_a_new_general_image_does_not_disturb_an_existing_primary_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $existingPrimary = ProductImage::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => null,
            'is_primary' => true,
        ]);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('create', data: [
                'images' => [UploadedFile::fake()->image('new-front.jpg')],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($existingPrimary->fresh()->is_primary);
        $this->assertSame(1, $product->images()->where('is_primary', true)->count());
    }

    public function test_adding_a_variant_image_does_not_affect_a_different_variants_primary_image(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());

        $product = Product::factory()->create();
        $variantA = ProductVariant::factory()->create(['product_id' => $product->id]);
        $variantB = ProductVariant::factory()->create(['product_id' => $product->id]);

        $primaryForA = ProductImage::factory()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variantA->id,
            'is_primary' => true,
        ]);

        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $product, 'pageClass' => EditProduct::class])
            ->callTableAction('addImage', $variantB, data: [
                'images' => [UploadedFile::fake()->image('variant-b.jpg')],
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($primaryForA->fresh()->is_primary);
    }
}
