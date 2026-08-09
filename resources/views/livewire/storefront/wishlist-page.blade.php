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
                        $image = $variant->images->firstWhere('is_primary', true)
                            ?? $variant->images->first()
                            ?? $product->images->firstWhere('is_primary', true)
                            ?? $product->images->first();
                    @endphp
                    <div wire:key="wishlist-item-{{ $wishlistItem->id }}" class="flex items-center gap-4 py-4">
                        @if ($image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}" alt="{{ $product->name }}" class="h-16 w-16 rounded-lg object-cover">
                        @else
                            <div class="flex h-16 w-16 items-center justify-center rounded-lg bg-zinc-100 text-zinc-400 dark:bg-zinc-800">
                                <x-app-icon name="folder" class="size-6" />
                            </div>
                        @endif

                        <div class="flex-1">
                            <p class="font-medium">{{ $product->name }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $variant->sku }}</p>
                            <p class="mt-1 text-sm font-medium">{{ $variant->price_formatted }}</p>
                        </div>

                        <x-button wire:click="addToCart({{ $variant->id }})" variant="primary">
                            {{ __('Add to cart') }}
                        </x-button>

                        <x-button wire:click="removeItem({{ $variant->id }})" wire:confirm="{{ __('Remove this item?') }}" variant="ghost">
                            <x-app-icon name="trash" class="size-4" />
                        </x-button>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif
</div>
