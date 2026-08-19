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
        ->with('images')
        ->first();

    $metaImage = $metaProduct
        ?->images
        ->sortByDesc('is_primary')
        ->first()
        ?->path;
@endphp

<x-layouts::storefront
    :title="$metaProduct?->name ?? __('Product')"
    :og-type="'product'"
    :og-description="$metaProduct?->meta_description ?: str($metaProduct?->description ?? '')->limit(160)->toString()"
    :og-image="$metaImage ? \Illuminate\Support\Facades\Storage::disk('public')->url($metaImage) : null"
>
    <livewire:storefront.product-detail-page :product-slug="$product" />
</x-layouts::storefront>
