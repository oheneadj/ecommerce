@php
    $formatMoney = fn (int $minorUnits): string => 'GH₵'.number_format($minorUnits / 100, 2);
    $variant = $this->selectedVariant;
    $galleryImages = $variant?->images->isNotEmpty() ? $variant->images : $product->images;
@endphp

<div class="space-y-8">
    <div class="grid gap-8 lg:grid-cols-2">
        <div class="space-y-3">
            <div class="flex aspect-square items-center justify-center rounded-lg bg-zinc-100 dark:bg-zinc-800">
                @if ($galleryImages->isNotEmpty())
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($galleryImages->first()->path) }}" alt="{{ $product->name }}" class="h-full w-full rounded-lg object-cover">
                @else
                    <x-app-icon name="folder" class="size-16 text-zinc-400" />
                @endif
            </div>

            @if ($galleryImages->count() > 1)
                <div class="grid grid-cols-5 gap-2">
                    @foreach ($galleryImages as $image)
                        <div class="flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-zinc-100 dark:bg-zinc-800">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                        </div>
                    @endforeach
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
                    {{ $variant->stock > 0 ? __('In stock') : __('Out of stock') }}
                </p>
            @else
                <p class="text-sm text-red-600 dark:text-red-400">{{ __('Currently unavailable') }}</p>
            @endif

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

            <div class="flex gap-3 pt-2">
                <x-button wire:click="addToCart" wire:loading.attr="disabled" wire:target="addToCart" icon="shopping-bag" variant="primary" :disabled="! $variant || $variant->stock <= 0">
                    <span wire:loading.remove wire:target="addToCart">{{ __('Add to cart') }}</span>
                    <span wire:loading wire:target="addToCart">{{ __('Adding…') }}</span>
                </x-button>
                <x-button wire:click="addToWishlist" wire:loading.attr="disabled" wire:target="addToWishlist" icon="heart" :disabled="! $variant">
                    {{ __('Add to wishlist') }}
                </x-button>
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
