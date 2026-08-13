<x-layouts::storefront :title="__('Home')">
    {{--
        Self-fetches the singleton settings row rather than relying on a
        variable from the wrapping layout component — the same
        `$store ??=` pattern layouts/storefront.blade.php and
        partials/head.blade.php already use, since slot content renders in
        this view's own scope, not the layout component's.
    --}}
    @php
        $homeStore = \App\Models\StoreSetting::current();
    @endphp

    <div class="space-y-16">
        <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-brand-primary to-brand-secondary px-6 py-14 text-center text-white sm:px-10">
            @if ($homeStore->logo_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($homeStore->logo_path) }}" alt="{{ $homeStore->business_name }}" class="mx-auto mb-4 h-14 w-auto">
            @endif
            <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">{{ $homeStore->business_name ?? config('app.name') }}</h1>
            @if ($homeStore->tagline)
                <p class="mx-auto mt-3 max-w-xl text-white/90">{{ $homeStore->tagline }}</p>
            @endif
            <a href="{{ route('products.index') }}" wire:navigate class="mt-6 inline-flex items-center gap-2 rounded-lg bg-white px-5 py-2.5 text-sm font-medium text-zinc-900 transition-transform duration-150 ease-out hover:-translate-y-0.5">
                {{ __('Shop now') }}
                <x-app-icon name="arrow-right" class="size-4" />
            </a>
        </section>

        @if ($brands->isNotEmpty())
            <section>
                <h2 class="text-lg font-medium">{{ __('Shop by brand') }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-8">
                    @foreach ($brands as $brand)
                        <a href="{{ route('products.index', ['brand' => $brand->slug]) }}" wire:navigate wire:key="brand-{{ $brand->id }}" class="flex aspect-square items-center justify-center rounded-lg border border-zinc-200 bg-white p-4 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brand->logo_path) }}" alt="{{ $brand->name }}" loading="lazy" class="max-h-10 w-full object-contain">
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($categories->isNotEmpty())
            <section>
                <h2 class="text-lg font-medium">{{ __('Shop by category') }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($categories as $category)
                        <a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate wire:key="category-{{ $category->id }}" class="flex flex-col items-center gap-2 rounded-lg border border-zinc-200 p-4 text-center transition-all duration-300 ease-out hover:-translate-y-0.5 hover:border-zinc-300 hover:shadow-lg dark:border-zinc-700 dark:hover:border-zinc-600">
                            <x-app-icon name="squares-2x2" class="size-6 text-brand-primary" />
                            <span class="text-sm font-medium">{{ $category->name }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium">{{ __('New arrivals') }}</h2>
                <a href="{{ route('products.index') }}" wire:navigate class="text-sm font-medium text-brand-primary hover:underline">{{ __('Shop all') }} &rarr;</a>
            </div>

            @if ($newProducts->isEmpty())
                <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No products available yet.') }}</p>
            @else
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($newProducts as $product)
                        <x-product-card :product="$product" wire:key="product-{{ $product->id }}" />
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-layouts::storefront>
