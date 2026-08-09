<x-layouts::storefront :title="__('Home')">
    <div class="space-y-12">
        @if ($categories->isNotEmpty())
            <section>
                <h2 class="text-lg font-medium">{{ __('Shop by category') }}</h2>
                <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($categories as $category)
                        <a href="{{ route('products.index', ['category' => $category->slug]) }}" wire:navigate class="rounded-lg border border-zinc-200 p-4 text-center text-sm font-medium hover:border-brand-primary dark:border-zinc-700">
                            {{ $category->name }}
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
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-layouts::storefront>
