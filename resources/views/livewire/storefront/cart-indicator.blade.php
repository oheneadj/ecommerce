<div
    class="relative"
    x-data
    x-on:click.outside="$wire.open = false"
    x-on:cart-item-added.window="$wire.open = true"
>
    <button
        type="button"
        wire:click="toggle"
        class="relative flex items-center gap-1.5 text-zinc-700 hover:text-brand-primary dark:text-zinc-300"
        aria-label="{{ __('Cart') }}"
    >
        <span class="relative">
            <x-app-icon name="shopping-bag" class="size-5" />
            @if ($this->itemCount > 0)
                <span class="absolute -top-2 -right-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-primary px-1 text-xs font-semibold text-white">
                    {{ $this->itemCount }}
                </span>
            @endif
        </span>
        <span class="hidden sm:inline">{{ __('Cart') }}</span>
    </button>

    @if ($open)
        <div class="absolute right-0 z-20 mt-2 w-80 rounded-lg border border-zinc-200 bg-white p-4 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
            @if (! $this->cart || $this->cart->items->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Your cart is empty.') }}</p>
            @else
                <div class="max-h-72 space-y-3 overflow-y-auto">
                    @foreach ($this->cart->items as $item)
                        @php
                            $variant = $item->productVariant;
                            $product = $variant->product;
                        @endphp
                        <div wire:key="preview-item-{{ $item->id }}" class="flex items-center gap-3">
                            <x-product-thumbnail :variant="$variant" :product="$product" class="h-12 w-12 shrink-0" />
                            <div class="flex-1 text-sm">
                                <p class="truncate font-medium">{{ $product->name }}</p>
                                <p class="text-zinc-500 dark:text-zinc-400">{{ $item->quantity }} &times; {{ $variant->price_formatted }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 flex items-center justify-between border-t border-zinc-200 pt-3 text-sm font-medium dark:border-zinc-700">
                    <span>{{ __('Subtotal') }}</span>
                    <span><x-money :amount="$this->subtotal" /></span>
                </div>

                <div class="mt-3 flex gap-2">
                    <x-button href="{{ route('cart.show') }}" wire:navigate class="flex-1 justify-center">{{ __('View cart') }}</x-button>
                    <x-button href="{{ route('checkout.show') }}" wire:navigate variant="primary" class="flex-1 justify-center">{{ __('Checkout') }}</x-button>
                </div>
            @endif
        </div>
    @endif
</div>
