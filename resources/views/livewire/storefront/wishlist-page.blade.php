<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('My Wishlist') }}</h1>

    @if ($this->items->isEmpty())
        <x-card>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Your wishlist is empty.') }}</p>
        </x-card>
    @else
        <x-card>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->items as $wishlistItem)
                    @php
                        $variant = $wishlistItem->productVariant;
                        $product = $variant->product;
                    @endphp
                    <div wire:key="wishlist-item-{{ $wishlistItem->id }}" class="flex flex-wrap items-center gap-4 py-4">
                        <a href="{{ route('products.show', $product) }}" wire:navigate class="-m-2 flex min-w-0 flex-1 basis-40 items-center gap-4 rounded-lg p-2 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <x-product-thumbnail :variant="$variant" :product="$product" class="h-16 w-16 shrink-0" />

                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $product->name }}</p>
                                <p class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $variant->sku }}</p>
                                <p class="mt-1 text-sm font-medium">{{ $variant->price_formatted }}</p>
                            </div>
                        </a>

                        <div class="ms-auto flex items-center gap-2 sm:ms-0">
                            <x-button wire:click="addToCart({{ $variant->id }})" variant="primary">
                                {{ __('Add to cart') }}
                            </x-button>

                            <x-button wire:click="removeItem({{ $variant->id }})" wire:confirm="{{ __('Remove this item?') }}" variant="ghost">
                                <x-app-icon name="trash" class="size-4" />
                            </x-button>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif
</div>
