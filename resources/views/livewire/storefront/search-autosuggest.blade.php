<div class="relative min-w-0 flex-1" x-data x-on:click.outside="$wire.close()" x-on:keydown.escape="$wire.close()">
    <form action="{{ route('products.index') }}" method="GET" role="search">
        <input
            type="search"
            name="search"
            wire:model.live.debounce.300ms="query"
            wire:focus="open = true"
            placeholder="{{ __('Search products…') }}"
            aria-label="{{ __('Search products') }}"
            autocomplete="off"
            maxlength="100"
            class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 focus:border-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-500"
        >
    </form>

    @if ($open)
        <div class="absolute left-0 right-0 z-20 mt-2 max-h-96 overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-lg">
            <div wire:loading wire:target="query" class="space-y-3 p-3">
                @for ($i = 0; $i < 3; $i++)
                    <div class="flex animate-pulse items-center gap-3">
                        <div class="h-10 w-10 shrink-0 rounded-lg bg-zinc-200"></div>
                        <div class="flex-1 space-y-1.5">
                            <div class="h-3 w-3/4 rounded bg-zinc-200"></div>
                            <div class="h-3 w-1/4 rounded bg-zinc-200"></div>
                        </div>
                    </div>
                @endfor
            </div>

            <div wire:loading.remove wire:target="query">
                @forelse ($this->suggestions as $product)
                    @php $variant = $product->variants->first(); @endphp
                    <a
                        wire:key="suggestion-{{ $product->id }}"
                        href="{{ route('products.show', $product) }}"
                        wire:navigate
                        wire:click="close"
                        class="flex items-center gap-3 p-3 transition-colors hover:bg-zinc-50"
                    >
                        <x-product-thumbnail :variant="$variant" :product="$product" class="h-10 w-10 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">{{ $product->name }}</p>
                            @if ($variant)
                                <p class="text-xs text-zinc-500">{{ $variant->price_formatted }}</p>
                            @endif
                        </div>
                    </a>
                @empty
                    @if (mb_strlen(trim($query)) >= 2)
                        <p class="p-3 text-sm text-zinc-500">{{ __('No products found.') }}</p>
                    @endif
                @endforelse

                @if ($this->suggestions->isNotEmpty())
                    <a
                        href="{{ route('products.index', ['search' => $query]) }}"
                        wire:navigate
                        wire:click="close"
                        class="block border-t border-zinc-200 p-3 text-center text-sm font-medium text-brand-primary hover:underline"
                    >
                        {{ __('View all results for ":query"', ['query' => $query]) }}
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
