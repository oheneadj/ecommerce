@php
    // Resolved here (not just left to the Livewire component inside) so
    // the <title>/Open Graph tags are present in the initial server-
    // rendered HTML — a link-preview crawler (WhatsApp, Facebook, etc.)
    // doesn't wait for Livewire to hydrate, it only ever sees this first
    // response. A soft lookup (not firstOrFail()) — if the slug doesn't
    // resolve to a real, active product, the head just falls back to
    // generic defaults and the Livewire component below is what actually
    // 404s the page.
    $metaProduct = \App\Models\Product::query()
        ->where('status', \App\Enums\ProductStatus::Active)
        ->where('slug', $product)
        ->with(['images', 'category', 'variants' => fn ($query) => $query->orderBy('price')])
        ->first();

    $metaImage = $metaProduct
        ?->images
        ->sortByDesc('is_primary')
        ->first()
        ?->path;

    $metaImageUrl = $metaImage ? \Illuminate\Support\Facades\Storage::disk('public')->url($metaImage) : null;

    // Product + BreadcrumbList structured data — Google reads this for
    // rich results (price, availability, star rating) and breadcrumb
    // trails in search. Only rendered when the product actually resolved
    // (matches the same soft-lookup fallback the OG tags above use).
    $metaJsonLd = null;

    if ($metaProduct !== null) {
        $cheapestVariant = $metaProduct->variants->first();

        $metaJsonLd = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $metaProduct->name,
                'description' => $metaProduct->meta_description ?: str($metaProduct->description ?? '')->limit(160)->toString(),
                'image' => $metaImageUrl,
                'sku' => $cheapestVariant?->sku,
                'brand' => $metaProduct->brand?->name ? ['@type' => 'Brand', 'name' => $metaProduct->brand->name] : null,
                'offers' => $cheapestVariant !== null ? [
                    '@type' => 'Offer',
                    'url' => route('products.show', $metaProduct->slug),
                    'priceCurrency' => config('app.currency', 'GHS'),
                    'price' => $cheapestVariant->price_decimal,
                    'availability' => $cheapestVariant->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                ] : null,
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => array_values(array_filter([
                    ['@type' => 'ListItem', 'position' => 1, 'name' => __('Shop'), 'item' => route('products.index')],
                    $metaProduct->category ? ['@type' => 'ListItem', 'position' => 2, 'name' => $metaProduct->category->name, 'item' => route('products.index').'?category='.$metaProduct->category->slug] : null,
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $metaProduct->name, 'item' => route('products.show', $metaProduct->slug)],
                ])),
            ],
        ];
    }
@endphp

<x-layouts::storefront
    :title="$metaProduct?->name ?? __('Product')"
    :og-type="'product'"
    :og-description="$metaProduct?->meta_description ?: str($metaProduct?->description ?? '')->limit(160)->toString()"
    :og-image="$metaImageUrl"
    :json-ld="$metaJsonLd"
>
    <livewire:storefront.product-detail-page :product-slug="$product" />
</x-layouts::storefront>
