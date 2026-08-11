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

            {{--
                A custom Alpine-driven slider rather than two overlapping
                native <input type="range"> elements — that approach relies
                on `pointer-events` tricks to let each thumb "win" over the
                other's full-width hit area, which is unreliable across
                browsers (dead zones, no click-to-jump on the track, thumb
                overflow at this track's 4px height) and gave a slider that
                genuinely didn't work. Dragging updates local Alpine state
                only, at 60fps with no server round trip; the actual
                minPrice/maxPrice filter (and the URL/results it drives)
                commits once via `commit()` when the drag ends or the track
                is clicked, not on every pixel of movement.
            --}}
            <x-card
                x-data="{
                    min: {{ (int) ($minPrice ?? 0) }},
                    max: {{ (int) ($maxPrice ?? $this->catalogMaxPrice) }},
                    ceiling: {{ $this->catalogMaxPrice }},
                    dragging: null,
                    valueFromEvent(event) {
                        const rect = $refs.track.getBoundingClientRect();
                        const clientX = event.touches ? event.touches[0].clientX : event.clientX;
                        const ratio = Math.min(1, Math.max(0, (clientX - rect.left) / rect.width));
                        return Math.round(ratio * this.ceiling);
                    },
                    startDrag(thumb, event) {
                        this.dragging = thumb;
                        event.preventDefault();
                    },
                    onMove(event) {
                        if (!this.dragging) return;
                        const value = this.valueFromEvent(event);
                        if (this.dragging === 'min') {
                            this.min = Math.min(value, this.max);
                        } else {
                            this.max = Math.max(value, this.min);
                        }
                    },
                    stopDrag() {
                        if (!this.dragging) return;
                        this.dragging = null;
                        this.commit();
                    },
                    onTrackClick(event) {
                        const value = this.valueFromEvent(event);
                        if (Math.abs(value - this.min) <= Math.abs(value - this.max)) {
                            this.min = Math.min(value, this.max);
                        } else {
                            this.max = Math.max(value, this.min);
                        }
                        this.commit();
                    },
                    commit() {
                        $wire.set('minPrice', this.min);
                        $wire.set('maxPrice', this.max);
                    },
                }"
                @mousemove.window="onMove($event)"
                @mouseup.window="stopDrag()"
                @touchmove.window="onMove($event)"
                @touchend.window="stopDrag()"
            >
                <h2 class="text-sm font-medium">{{ __('Price (GH₵)') }}</h2>
                <div class="mt-3 flex items-center justify-between text-xs text-zinc-500 dark:text-zinc-400">
                    <span x-text="'GH₵' + min"></span>
                    <span x-text="'GH₵' + max"></span>
                </div>
                <div x-ref="track" @click="onTrackClick($event)" class="relative mt-4 h-1 cursor-pointer rounded-full bg-zinc-200 dark:bg-zinc-700">
                    <div
                        class="absolute h-1 rounded-full bg-brand-primary"
                        :style="`left: ${(min / ceiling) * 100}%; right: ${100 - (max / ceiling) * 100}%`"
                    ></div>
                    <div
                        class="absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 cursor-pointer rounded-full border-2 border-white bg-brand-primary shadow"
                        :style="`left: ${(min / ceiling) * 100}%`"
                        @mousedown="startDrag('min', $event)"
                        @touchstart="startDrag('min', $event)"
                        @click.stop
                        role="slider"
                        :aria-valuenow="min"
                        aria-valuemin="0"
                        :aria-valuemax="ceiling"
                        aria-label="{{ __('Minimum price') }}"
                        tabindex="0"
                        @keydown.left="min = Math.max(0, min - 1); commit()"
                        @keydown.right="min = Math.min(max, min + 1); commit()"
                    ></div>
                    <div
                        class="absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 cursor-pointer rounded-full border-2 border-white bg-brand-primary shadow"
                        :style="`left: ${(max / ceiling) * 100}%`"
                        @mousedown="startDrag('max', $event)"
                        @touchstart="startDrag('max', $event)"
                        @click.stop
                        role="slider"
                        :aria-valuenow="max"
                        aria-valuemin="0"
                        :aria-valuemax="ceiling"
                        aria-label="{{ __('Maximum price') }}"
                        tabindex="0"
                        @keydown.left="max = Math.max(min, max - 1); commit()"
                        @keydown.right="max = Math.min(ceiling, max + 1); commit()"
                    ></div>
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
