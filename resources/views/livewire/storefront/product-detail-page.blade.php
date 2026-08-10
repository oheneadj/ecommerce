@php
    $variant = $this->selectedVariant;
    $galleryImages = $variant?->galleryImages() ?? $product->images;
@endphp

<div class="space-y-8">
    <div class="grid gap-8 lg:grid-cols-2">
        <div
            wire:key="gallery-{{ $variant?->id }}"
            class="space-y-3"
            x-data="{
                images: {{ \Illuminate\Support\Js::from($galleryImages->map(fn ($image) => \Illuminate\Support\Facades\Storage::disk('public')->url($image->path))->all()) }},
                selected: 0,
                lightboxOpen: false,
                next() { this.selected = (this.selected + 1) % this.images.length; },
                prev() { this.selected = (this.selected - 1 + this.images.length) % this.images.length; },
            }"
        >
            <div class="flex aspect-square items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                @if ($galleryImages->isNotEmpty())
                    <img
                        :src="images[selected]"
                        @click="lightboxOpen = true"
                        alt="{{ $product->name }}"
                        loading="eager"
                        fetchpriority="high"
                        class="h-full w-full cursor-zoom-in rounded-lg object-cover"
                    >
                @else
                    <x-app-icon name="folder" class="size-16 text-zinc-400" />
                @endif
            </div>

            @if ($galleryImages->count() > 1)
                <div class="grid grid-cols-5 gap-2">
                    @foreach ($galleryImages as $index => $image)
                        <button
                            type="button"
                            @click="selected = {{ $index }}"
                            class="flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-zinc-100 ring-2 ring-offset-2 ring-offset-white dark:bg-zinc-800 dark:ring-offset-zinc-900"
                            :class="selected === {{ $index }} ? 'ring-brand-primary' : 'ring-transparent'"
                        >
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="{{ $product->name }}" loading="lazy" class="h-full w-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($galleryImages->isNotEmpty())
                <div
                    x-show="lightboxOpen"
                    x-cloak
                    x-on:keydown.escape.window="lightboxOpen = false"
                    x-on:keydown.arrow-right.window="lightboxOpen && next()"
                    x-on:keydown.arrow-left.window="lightboxOpen && prev()"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                >
                    <div x-show="lightboxOpen" x-transition.opacity x-on:click="lightboxOpen = false" class="fixed inset-0 bg-black/90"></div>

                    <button
                        type="button"
                        @click="lightboxOpen = false"
                        class="absolute top-4 right-4 z-10 text-white/80 hover:text-white"
                        aria-label="{{ __('Close') }}"
                    >
                        <x-app-icon name="x-circle" class="size-8" />
                    </button>

                    <div x-show="lightboxOpen" x-transition class="relative flex max-h-full max-w-5xl items-center gap-3">
                        @if ($galleryImages->count() > 1)
                            <button type="button" @click.stop="prev()" class="z-10 shrink-0 rounded-full bg-zinc-900/70 p-2 text-white hover:bg-zinc-900/90" aria-label="{{ __('Previous image') }}">
                                <x-app-icon name="chevron-up" class="size-6 -rotate-90" />
                            </button>
                        @endif

                        <img :src="images[selected]" alt="{{ $product->name }}" class="max-h-[85vh] min-w-0 rounded-lg object-contain" @click.stop>

                        @if ($galleryImages->count() > 1)
                            <button type="button" @click.stop="next()" class="z-10 shrink-0 rounded-full bg-zinc-900/70 p-2 text-white hover:bg-zinc-900/90" aria-label="{{ __('Next image') }}">
                                <x-app-icon name="chevron-up" class="size-6 rotate-90" />
                            </button>

                            <p class="absolute -bottom-8 left-1/2 -translate-x-1/2 text-sm text-white/70" x-text="`${selected + 1} / ${images.length}`"></p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div>
                @if ($product->brand)
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $product->brand->name }}</p>
                @endif
                <h1 class="text-2xl font-semibold">{{ $product->name }}</h1>
                @if ($this->reviews->isNotEmpty())
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('★ :rating (:count reviews)', ['rating' => $this->averageRating, 'count' => $this->reviews->count()]) }}
                    </p>
                @endif
            </div>

            @if ($variant)
                <p class="text-2xl font-semibold">{{ $variant->price_formatted }}</p>
                <p class="text-sm {{ $variant->stock > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $variant->stock > 0 ? __(':count in stock', ['count' => $variant->stock]) : __('Out of stock') }}
                </p>
            @else
                <p class="text-sm text-red-600 dark:text-red-400">{{ __('Currently unavailable') }}</p>
            @endif

            @if ($this->hasAttributeSelector)
                @foreach ($product->attributes as $attribute)
                    <div wire:key="attribute-{{ $attribute->id }}">
                        <p class="text-sm font-medium">{{ $attribute->name }}</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($attribute->terms as $term)
                                <button
                                    type="button"
                                    wire:click="selectTerm({{ $attribute->id }}, {{ $term->id }})"
                                    class="rounded-lg border px-3 py-1.5 text-sm {{ ($selectedTermIds[$attribute->id] ?? null) === $term->id ? 'border-brand-primary text-brand-primary' : 'border-zinc-300 dark:border-zinc-600' }}"
                                >
                                    {{ $term->value }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @elseif ($product->variants->count() > 1)
                {{-- No global Attribute is attached to this product, so there's
                     nothing for the loop above to render — without this, every
                     variant past the first would be permanently unreachable. --}}
                <div>
                    <p class="text-sm font-medium">{{ __('Options') }}</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($product->variants as $productVariant)
                            <button
                                type="button"
                                wire:key="variant-option-{{ $productVariant->id }}"
                                wire:click="selectVariant({{ $productVariant->id }})"
                                class="rounded-lg border px-3 py-1.5 text-sm {{ $variant?->id === $productVariant->id ? 'border-brand-primary text-brand-primary' : 'border-zinc-300 dark:border-zinc-600' }}"
                            >
                                {{ $productVariant->display_label }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            <div
                x-data="{ justAdded: false, autoHideTimer: null }"
                x-on:cart-item-added.window="
                    justAdded = true;
                    clearTimeout(autoHideTimer);
                    autoHideTimer = setTimeout(() => justAdded = false, 4000);
                "
            >
                <div class="flex gap-3 pt-2">
                    <x-button wire:click="addToCart" wire:loading.attr="disabled" wire:target="addToCart" icon="shopping-bag" variant="primary" :disabled="! $variant || $variant->stock <= 0">
                        <span wire:loading.remove wire:target="addToCart">{{ __('Add to cart') }}</span>
                        <span wire:loading wire:target="addToCart">{{ __('Adding…') }}</span>
                    </x-button>
                    <x-button
                        wire:click="toggleWishlist"
                        wire:loading.attr="disabled"
                        wire:target="toggleWishlist"
                        icon="heart"
                        :icon-filled="$this->isWishlisted"
                        :variant="$this->isWishlisted ? 'filled' : 'outline'"
                        :disabled="! $variant"
                    >
                        {{ $this->isWishlisted ? __('In wishlist') : __('Add to wishlist') }}
                    </x-button>
                </div>

                <div x-show="justAdded" x-transition x-cloak class="pt-3">
                    <x-button href="{{ route('checkout.show') }}" wire:navigate variant="primary" icon="arrow-right" class="w-full justify-center">
                        {{ __('View Checkout') }}
                    </x-button>
                </div>
            </div>

            @if ($product->description)
                <div class="border-t border-zinc-200 pt-4 text-sm text-zinc-700 dark:border-zinc-700 dark:text-zinc-300">
                    {{ $product->description }}
                </div>
            @endif
        </div>
    </div>

    <div>
        <h2 class="text-lg font-medium">{{ __('Reviews') }}</h2>
        @if ($this->reviews->isEmpty())
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No reviews yet.') }}</p>
        @else
            <div class="mt-4 space-y-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->reviews as $review)
                    <div wire:key="review-{{ $review->id }}" class="pt-4 first:pt-0">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium">{{ $review->user->name ?? __('Anonymous') }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</p>
                        </div>
                        @if ($review->title)
                            <p class="mt-1 text-sm font-medium">{{ $review->title }}</p>
                        @endif
                        <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $review->body }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
