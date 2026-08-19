<?php

/**
 * Creates a full, independent copy of a product for use as a starting
 * point — e.g. "same shirt, different color line."
 */

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Actions\Inventory\RecordStockMovement;
use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Copies the product's own fields, its enabled global attributes, every
 * variant (custom attribute values, shared global attribute-term links,
 * and its own images), and every product-level image — physically copied
 * to a new file on disk, never a second ProductImage row pointing at the
 * original file, since `ProductImageObserver` deletes the underlying file
 * whenever *any* row referencing it is deleted: two rows sharing one path
 * would mean deleting either product's image deletes the file out from
 * under the other.
 *
 * Never copied: reviews and stock movements/reservations — real history
 * belonging to the original product, not template data for a copy.
 * Always created as Draft, regardless of the original's status, so a
 * duplicate is never accidentally live before an admin reviews it.
 */
class DuplicateProduct
{
    use AsAction;

    public function handle(Product $product, ?User $actor = null): Product
    {
        return DB::transaction(function () use ($product, $actor): Product {
            $copy = $this->duplicateProduct($product);

            $copy->attributes()->sync($product->attributes()->pluck('attributes.id'));

            foreach ($product->images()->whereNull('product_variant_id')->get() as $image) {
                $this->duplicateImage($image, $copy);
            }

            foreach ($product->variants()->get() as $variant) {
                $this->duplicateVariant($variant, $copy, $actor);
            }

            return $copy;
        });
    }

    /**
     * Copies the product's own fields — " (Copy)" appended to the name so
     * it's visually distinguishable in the admin list, a fresh unique
     * slug (the same row-ID-suffix pattern `DeleteProduct` uses to free a
     * slug on delete — the new row's own ID is always unique, so this can
     * never collide), and status forced to Draft.
     */
    private function duplicateProduct(Product $product): Product
    {
        $copy = Product::query()->create([
            'category_id' => $product->category_id,
            'brand_id' => $product->brand_id,
            'name' => "{$product->name} (Copy)",
            'slug' => (string) Str::uuid(),
            'description' => $product->description,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'status' => ProductStatus::Draft,
        ]);

        $copy->update(['slug' => "{$product->slug}-copy-{$copy->id}"]);

        return $copy;
    }

    /**
     * Physically copies the image file to a new path — see the class
     * docblock for why sharing the original file between two ProductImage
     * rows isn't safe.
     */
    private function duplicateImage(ProductImage $image, Product $newProduct, ?ProductVariant $newVariant = null): void
    {
        $newPath = 'product-images/'.Str::uuid().'.'.pathinfo($image->path, PATHINFO_EXTENSION);

        Storage::disk('public')->copy($image->path, $newPath);

        $newProduct->images()->create([
            'product_variant_id' => $newVariant?->id,
            'attribute_term_id' => $image->attribute_term_id,
            'path' => $newPath,
            'sort_order' => $image->sort_order,
            'is_primary' => $image->is_primary,
        ]);
    }

    /**
     * Copies a variant's own fields (fresh SKU via the same ID-suffix
     * pattern as the product's slug), its custom attribute values, its
     * shared global attribute-term links (the terms themselves are
     * catalog-wide, so only the link needs copying, not the term), and
     * its own images. Stock is never copied directly onto the new row —
     * created at 0 and, if the original had any, applied via
     * RecordStockMovement so the new variant's stock_movements ledger
     * always explains its cached stock total, same invariant
     * GenerateProductVariants already enforces at creation time.
     */
    private function duplicateVariant(ProductVariant $variant, Product $newProduct, ?User $actor): void
    {
        $newVariant = $newProduct->variants()->create([
            'sku' => (string) Str::uuid(),
            'price' => $variant->price,
            'stock' => 0,
            'low_stock_threshold' => $variant->low_stock_threshold,
            'status' => $variant->status,
        ]);

        $newVariant->update(['sku' => "{$variant->sku}-COPY-{$newVariant->id}"]);

        if ($variant->stock > 0) {
            RecordStockMovement::run($newVariant, StockMovementType::Restock, $variant->stock, $actor, 'Initial stock copied from duplicated product');
        }

        $newVariant->attributeTerms()->sync($variant->attributeTerms()->pluck('attribute_terms.id'));

        foreach ($variant->attributeValues()->get() as $attributeValue) {
            $newVariant->attributeValues()->create([
                'attribute_name' => $attributeValue->attribute_name,
                'value' => $attributeValue->value,
            ]);
        }

        foreach ($variant->images()->get() as $image) {
            $this->duplicateImage($image, $newProduct, $newVariant);
        }
    }
}
