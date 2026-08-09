<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Shop') }}</h1>

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="space-y-6">
            <x-card>
                <x-input wire:model.live.debounce.400ms="search" type="search" :placeholder="__('Search products…')" />
            </x-card>

            <x-card>
                <h2 class="text-sm font-medium">{{ __('Category') }}</h2>
                <div class="mt-2 space-y-1">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="category" value="">
                        {{ __('All') }}
                    </label>
                    @foreach ($this->categories as $categoryOption)
                        <label wire:key="category-{{ $categoryOption->id }}" class="flex items-center gap-2 text-sm">
                            <input type="radio" wire:model.live="category" value="{{ $categoryOption->slug }}">
                            {{ $categoryOption->name }}
                        </label>
                    @endforeach
                </div>
            </x-card>

            @if ($this->brands->isNotEmpty())
                <x-card>
                    <h2 class="text-sm font-medium">{{ __('Brand') }}</h2>
                    <div class="mt-2 space-y-1">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" wire:model.live="brand" value="">
                            {{ __('All') }}
                        </label>
                        @foreach ($this->brands as $brandOption)
                            <label wire:key="brand-{{ $brandOption->id }}" class="flex items-center gap-2 text-sm">
                                <input type="radio" wire:model.live="brand" value="{{ $brandOption->slug }}">
                                {{ $brandOption->name }}
                            </label>
                        @endforeach
                    </div>
                </x-card>
            @endif

            <x-card>
                <h2 class="text-sm font-medium">{{ __('Price (GH₵)') }}</h2>
                <div class="mt-2 flex items-center gap-2">
                    <x-input wire:model.live.debounce.400ms="minPrice" type="number" :placeholder="__('Min')" />
                    <x-input wire:model.live.debounce.400ms="maxPrice" type="number" :placeholder="__('Max')" />
                </div>
            </x-card>

            <button wire:click="resetFilters" type="button" class="text-sm font-medium text-brand-primary hover:underline">
                {{ __('Clear filters') }}
            </button>
        </div>

        <div class="lg:col-span-3">
            @if ($this->products->isEmpty())
                <x-card>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No products match your filters.') }}</p>
                </x-card>
            @else
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    @foreach ($this->products as $product)
                        <x-product-card wire:key="product-{{ $product->id }}" :product="$product" />
                    @endforeach
                </div>

                <div class="mt-6">
                    {{ $this->products->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
