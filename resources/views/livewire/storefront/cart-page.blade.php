@php
    $formatMoney = fn (int $minorUnits): string => 'GH₵'.number_format($minorUnits / 100, 2);
@endphp

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
                        $image = $variant->images->firstWhere('is_primary', true)
                            ?? $variant->images->first()
                            ?? $product->images->firstWhere('is_primary', true)
                            ?? $product->images->first();
                    @endphp
                    <div wire:key="cart-item-{{ $item->id }}" class="flex items-center gap-4 py-4">
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

                        <div class="flex items-center gap-2">
                            <input
                                type="number"
                                min="0"
                                value="{{ $item->quantity }}"
                                wire:change="updateQuantity({{ $variant->id }}, $event.target.value)"
                                class="w-16 rounded-lg border border-zinc-300 bg-white px-2 py-1 text-center text-sm dark:border-zinc-600 dark:bg-zinc-800"
                            >
                        </div>

                        <div class="w-24 text-right font-medium">
                            {{ $formatMoney($variant->price * $item->quantity) }}
                        </div>

                        <x-button wire:click="removeItem({{ $variant->id }})" wire:confirm="{{ __('Remove this item?') }}" variant="ghost">
                            <x-app-icon name="trash" class="size-4" />
                        </x-button>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex items-center justify-between border-t border-zinc-200 pt-4 dark:border-zinc-700">
                <span class="text-lg font-medium">{{ __('Subtotal') }}</span>
                <span class="text-lg font-semibold">{{ $formatMoney($this->subtotal) }}</span>
            </div>
        </x-card>

        <div class="flex justify-end">
            <x-button variant="primary" href="{{ route('checkout.show') }}">{{ __('Proceed to checkout') }}</x-button>
        </div>
    @endif
</div>
