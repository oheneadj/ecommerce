<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('My Cart') }}</h1>

    @if ($this->cart->items->isEmpty())
        <x-card>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Your cart is empty.') }}</p>
        </x-card>
    @else
        <x-card>
            <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @foreach ($this->cart->items as $item)
                    @php
                        $variant = $item->productVariant;
                        $product = $variant->product;
                    @endphp
                    <div wire:key="cart-item-{{ $item->id }}" class="flex items-center gap-4 rounded-lg p-4 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/30">
                        <x-product-thumbnail :variant="$variant" :product="$product" class="h-16 w-16" />

                        <div class="flex-1">
                            <p class="font-medium">{{ $product->name }}</p>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $variant->sku }}</p>
                            <p class="mt-1 text-sm font-medium">{{ $variant->price_formatted }}</p>
                        </div>

                        <div class="flex items-center gap-2 transition-opacity duration-150" wire:loading.class="opacity-50" wire:target="updateQuantity({{ $variant->id }}, {{ max(0, $item->quantity - 1) }}), updateQuantity({{ $variant->id }}, {{ $item->quantity + 1 }})">
                            <button
                                type="button"
                                wire:click="updateQuantity({{ $variant->id }}, {{ $item->quantity - 1 }})"
                                aria-label="{{ __('Decrease quantity') }}"
                                class="flex size-8 items-center justify-center rounded-lg border border-zinc-300 transition-colors hover:bg-zinc-50 active:scale-95 dark:border-zinc-600 dark:hover:bg-zinc-800"
                            >
                                <x-app-icon name="minus" class="size-4" />
                            </button>
                            <span class="w-8 text-center text-sm">{{ $item->quantity }}</span>
                            <button
                                type="button"
                                wire:click="updateQuantity({{ $variant->id }}, {{ $item->quantity + 1 }})"
                                aria-label="{{ __('Increase quantity') }}"
                                @disabled($item->quantity >= $variant->stock)
                                class="flex size-8 items-center justify-center rounded-lg border border-zinc-300 transition-colors hover:bg-zinc-50 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent dark:border-zinc-600 dark:hover:bg-zinc-800"
                            >
                                <x-app-icon name="plus" class="size-4" />
                            </button>
                        </div>
                        @if ($item->quantity >= $variant->stock)
                            <p class="text-xs text-amber-600 dark:text-amber-400">{{ __('Max stock reached') }}</p>
                        @endif

                        <div class="w-24 text-right font-medium">
                            <x-money :amount="$variant->price * $item->quantity" />
                        </div>

                        <x-button wire:click="removeItem({{ $variant->id }})" wire:confirm="{{ __('Remove this item?') }}" variant="ghost">
                            <x-app-icon name="trash" class="size-4" />
                        </x-button>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <span class="text-lg font-medium">{{ __('Subtotal') }}</span>
                <span class="text-lg font-semibold"><x-money :amount="$this->subtotal" /></span>
            </div>
        </x-card>

        <div class="flex justify-end">
            <x-button variant="primary" href="{{ route('checkout.show') }}">{{ __('Proceed to checkout') }}</x-button>
        </div>
    @endif
</div>
