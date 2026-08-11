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

            @foreach ($this->filterableAttributes as $attribute)
                @php
                    $availableTermIds = $this->availableTermIdsByAttribute[$attribute->id] ?? [];
                @endphp
                {{-- An attribute with nothing reachable under the current
                     filters gets no group at all, not just an empty one. --}}
                @if (count($availableTermIds) > 0)
                    <x-card wire:key="attribute-filter-{{ $attribute->id }}">
                        <h2 class="text-sm font-medium">{{ $attribute->name }}</h2>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($attribute->terms as $term)
                                @php
                                    $isSelected = in_array($term->id, $attributeFilters[$attribute->id] ?? [], true);
                                    $isAvailable = in_array($term->id, $availableTermIds, true);
                                @endphp
                                {{-- Not `disabled` when unavailable — same reasoning as
                                     the product detail page's variant selector: greyed
                                     styling is a hint, not a lock, so picking it still
                                     updates the other filters instead of being a dead
                                     end. --}}
                                <button
                                    type="button"
                                    wire:click="toggleAttributeTerm({{ $attribute->id }}, {{ $term->id }})"
                                    @class([
                                        'rounded-lg border px-3 py-1.5 text-sm',
                                        'border-brand-primary text-brand-primary' => $isSelected,
                                        'border-zinc-300 dark:border-zinc-600' => ! $isSelected && $isAvailable,
                                        'border-zinc-200 text-zinc-400 line-through cursor-not-allowed dark:border-zinc-700 dark:text-zinc-600' => ! $isSelected && ! $isAvailable,
                                    ])
                                >
                                    {{ $term->value }}
                                </button>
                            @endforeach
                        </div>
                    </x-card>
                @endif
            @endforeach

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
