<div class="space-y-6" x-data="{ filtersOpen: false }">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Shop') }}</h1>

        {{--
            Mobile only — the sidebar becomes a slide-over below lg:
            (previously it just stacked above the product grid, forcing a
            long scroll past every filter group before reaching a single
            product).
        --}}
        <button
            type="button"
            @click="filtersOpen = true"
            class="flex items-center gap-1.5 rounded-lg border border-zinc-300 px-3 py-1.5 text-sm font-medium lg:hidden dark:border-zinc-600"
        >
            <x-app-icon name="funnel" class="size-4" />
            {{ __('Filters') }}
        </button>
    </div>

    <div class="grid gap-6 lg:grid-cols-4">
        {{-- Backdrop — mobile only, closes the slide-over on tap. --}}
        <div
            x-show="filtersOpen"
            x-cloak
            x-transition.opacity
            @click="filtersOpen = false"
            class="fixed inset-0 z-40 bg-black/50 lg:hidden"
        ></div>

        {{--
            The category/brand lists themselves are catalog-wide and never
            change with filters, but the attribute-term buttons below do
            (their available/greyed-out state is recomputed per filter
            request) — dim the whole sidebar during that same request so
            a stale-looking state never sits there unexplained, same
            wire:loading.class pattern the cart page uses for its
            quantity controls.

            Below lg: this panel is a fixed slide-over (translated
            off-screen until filtersOpen), not part of the document flow —
            at lg:+ every mobile-only class is overridden back to the
            original static-sidebar layout.
        --}}
        <div
            class="fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] space-y-6 overflow-y-auto bg-white p-4 shadow-xl transition-transform duration-150 lg:static lg:z-auto lg:w-auto lg:max-w-none lg:translate-x-0 lg:overflow-visible lg:bg-transparent lg:p-0 lg:shadow-none dark:bg-zinc-900 lg:dark:bg-transparent"
            :class="filtersOpen ? 'translate-x-0' : '-translate-x-full'"
            wire:loading.class="opacity-50"
            wire:target="search,category,brand,minPrice,maxPrice,toggleAttributeTerm,resetFilters"
        >
            <div class="flex items-center justify-between lg:hidden">
                <h2 class="text-lg font-semibold">{{ __('Filters') }}</h2>
                <button type="button" @click="filtersOpen = false" aria-label="{{ __('Close') }}" class="p-1 text-zinc-500 dark:text-zinc-400">
                    <x-app-icon name="x-circle" class="size-6" />
                </button>
            </div>
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
                                @if ($brandOption->logo_path)
                                    <img
                                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($brandOption->logo_path) }}"
                                        alt=""
                                        class="size-5 shrink-0 rounded-full object-contain"
                                    >
                                @else
                                    <span class="flex size-5 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 dark:bg-zinc-800">
                                        <x-app-icon name="folder" class="size-3" />
                                    </span>
                                @endif
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
                genuinely didn't work.

                A single `mousedown`/`touchstart` handler on the TRACK (not
                a separate `click` handler) does both click-to-jump and
                drag-start — a first version used a distinct `click`
                listener for jumping and `mousedown` on each thumb for
                dragging, but a fast drag leaves the thumb's DOM position
                one Alpine reactivity tick behind the cursor, so the
                browser's synthetic `click` on release lands on the track
                (not the thumb — `@click.stop` on the thumb never runs) and
                fires a second, slightly different jump right after the
                drag already committed. That's what looked like the slider
                "moving by itself" after letting go. One code path for both
                interactions removes the race entirely.

                Dragging updates local Alpine state only, at 60fps with no
                server round trip; the actual minPrice/maxPrice filter (and
                the URL/results it drives) commits once via `commit()` when
                the pointer is released.
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
                    startDrag(event) {
                        const value = this.valueFromEvent(event);
                        this.dragging = Math.abs(value - this.min) <= Math.abs(value - this.max) ? 'min' : 'max';
                        this.applyValue(value);
                        event.preventDefault();
                    },
                    onMove(event) {
                        if (!this.dragging) return;
                        this.applyValue(this.valueFromEvent(event));
                    },
                    applyValue(value) {
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
                <div
                    x-ref="track"
                    @mousedown="startDrag($event)"
                    @touchstart="startDrag($event)"
                    class="relative mt-4 h-1 cursor-pointer rounded-full bg-zinc-200 dark:bg-zinc-700"
                >
                    <div
                        class="absolute h-1 rounded-full bg-brand-primary"
                        :style="`left: ${(min / ceiling) * 100}%; right: ${100 - (max / ceiling) * 100}%`"
                    ></div>
                    <div
                        class="absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 cursor-pointer rounded-full border-2 border-white bg-brand-primary shadow"
                        :style="`left: ${(min / ceiling) * 100}%`"
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
            {{--
                Filter-change loading state only — never shown on the
                initial page load, so product content stays fully
                server-rendered and crawlable (see CHANGELOG: lazy-loading
                the whole listing was deliberately rejected for this same
                reason). Scoped via wire:target to just the filter
                inputs/actions above, so it never fires for unrelated
                interactions elsewhere on the page (e.g. the navbar cart).
            --}}
            <div wire:loading wire:target="search,category,brand,minPrice,maxPrice,toggleAttributeTerm,resetFilters" class="grid grid-cols-2 gap-4 sm:grid-cols-3" aria-hidden="true" aria-busy="true">
                @for ($i = 0; $i < 9; $i++)
                    <div class="animate-pulse space-y-2">
                        <div class="aspect-square rounded-lg bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="h-3 w-3/4 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                        <div class="h-3 w-1/3 rounded bg-zinc-200 dark:bg-zinc-700"></div>
                    </div>
                @endfor
            </div>

            <div wire:loading.remove wire:target="search,category,brand,minPrice,maxPrice,toggleAttributeTerm,resetFilters">
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
</div>
