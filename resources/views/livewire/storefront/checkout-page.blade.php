<div class="space-y-6">
    <h1 class="text-2xl font-semibold">{{ __('Checkout') }}</h1>

    @error('cart')
        <div class="rounded-lg bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/40 dark:text-red-300">{{ $message }}</div>
    @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @auth
                <x-card>
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-medium">{{ __('Delivery address') }}</h2>
                        <a href="{{ route('account.addresses') }}" wire:navigate class="text-sm font-medium text-brand-primary hover:underline">{{ __('Manage addresses') }}</a>
                    </div>

                    @if ($this->addresses->isEmpty())
                        <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('You have no saved addresses.') }}
                            <a href="{{ route('account.addresses') }}" wire:navigate class="font-medium text-brand-primary hover:underline">{{ __('Add one') }}</a>
                        </p>
                    @else
                        <div class="mt-4 space-y-2">
                            @foreach ($this->addresses as $address)
                                <label wire:key="checkout-address-{{ $address->id }}" class="flex cursor-pointer items-start gap-3 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                    <input type="radio" wire:model="selectedAddressId" value="{{ $address->id }}" class="mt-1">
                                    <span class="text-sm">
                                        <span class="font-medium">{{ $address->label ?: $address->recipient_name }}</span><br>
                                        {{ $address->recipient_name }}, {{ $address->phone }}<br>
                                        {{ $address->line1 }}@if ($address->line2), {{ $address->line2 }}@endif, {{ $address->city }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('selectedAddressId')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </x-card>
            @else
                <x-card>
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-medium">{{ __('Your details') }}</h2>
                        <a href="{{ route('login.phone') }}" class="text-sm font-medium text-brand-primary hover:underline">{{ __('Sign in instead') }}</a>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input wire:model="guestName" :placeholder="__('Full name')" />
                        </div>
                        <div>
                            <x-input wire:model="guestPhone" type="tel" :placeholder="__('Phone number')" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input wire:model="guestEmail" type="email" :placeholder="__('Email address')" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input wire:model="guestLine1" :placeholder="__('Address line 1')" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input wire:model="guestLine2" :placeholder="__('Address line 2 (optional)')" />
                        </div>
                        <div>
                            <x-input wire:model="guestCity" :placeholder="__('City')" />
                        </div>
                        <div>
                            <x-input wire:model="guestRegion" :placeholder="__('Region (optional)')" />
                        </div>
                    </div>
                </x-card>
            @endauth

            <x-card>
                <h2 class="text-lg font-medium">{{ __('Shipping method') }}</h2>

                @if ($this->shippingMethods->isEmpty())
                    <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No shipping methods are currently available.') }}</p>
                @else
                    <div class="mt-4 space-y-2">
                        @foreach ($this->shippingMethods as $method)
                            <label wire:key="checkout-shipping-{{ $method->id }}" class="flex cursor-pointer items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                                <span class="flex items-center gap-3 text-sm">
                                    <input type="radio" wire:model.live="selectedShippingMethodId" value="{{ $method->id }}">
                                    {{ $method->name }}
                                </span>
                                <span class="text-sm font-medium">{{ $method->cost_formatted }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif
                @error('selectedShippingMethodId')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </x-card>

            <x-card>
                <h2 class="text-lg font-medium">{{ __('Payment method') }}</h2>
                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('You will pay via :provider.', ['provider' => $this->activePaymentProviderLabel]) }}
                </p>
            </x-card>
        </div>

        <div>
            <x-card>
                <h2 class="text-lg font-medium">{{ __('Order summary') }}</h2>

                <div class="mt-4 space-y-2 text-sm">
                    @foreach ($this->cart->items as $item)
                        <div wire:key="summary-item-{{ $item->id }}" class="flex justify-between">
                            <span>{{ $item->productVariant->product->name }} &times; {{ $item->quantity }}</span>
                            <span><x-money :amount="$item->productVariant->price * $item->quantity" /></span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 space-y-2 border-t border-zinc-200 pt-4 text-sm dark:border-zinc-700">
                    @if ($this->appliedCoupon)
                        <div class="flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400">
                            <span class="font-medium">{{ __('Coupon ":code" applied', ['code' => $this->appliedCoupon->code]) }}</span>
                            <button type="button" wire:click="removeCoupon" class="text-xs font-medium underline">
                                {{ __('Remove') }}
                            </button>
                        </div>
                    @else
                        <div class="flex items-start gap-2">
                            <div class="flex-1">
                                <x-input wire:model="couponCode" placeholder="{{ __('Coupon code') }}" />
                            </div>
                            <x-button type="button" wire:click="applyCoupon" wire:loading.attr="disabled" wire:target="applyCoupon">
                                <span wire:loading.remove wire:target="applyCoupon">{{ __('Apply') }}</span>
                                <span wire:loading wire:target="applyCoupon">{{ __('Applying…') }}</span>
                            </x-button>
                        </div>
                    @endif
                </div>

                <div class="mt-4 space-y-2 border-t border-zinc-200 pt-4 text-sm transition-opacity duration-150 dark:border-zinc-700" wire:loading.class="opacity-50" wire:target="selectedShippingMethodId,applyCoupon,removeCoupon">
                    <div class="flex justify-between"><span>{{ __('Subtotal') }}</span><span><x-money :amount="$this->subtotal" /></span></div>
                    @if ($this->discountAmount > 0)
                        <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                            <span>{{ __('Discount') }}</span>
                            <span>-<x-money :amount="$this->discountAmount" /></span>
                        </div>
                    @endif
                    <div class="flex justify-between"><span>{{ __('Tax') }}</span><span><x-money :amount="$this->taxEstimate" /></span></div>
                    <div class="flex justify-between"><span>{{ __('Shipping') }}</span><span><x-money :amount="$this->effectiveShippingCost" /></span></div>
                    <div class="flex justify-between text-base font-semibold"><span>{{ __('Total') }}</span><span><x-money :amount="$this->estimatedTotal" /></span></div>
                </div>

                <x-button wire:click="placeOrder" variant="primary" class="mt-4 w-full">
                    <span wire:loading.remove wire:target="placeOrder">{{ __('Place order') }}</span>
                    <span wire:loading wire:target="placeOrder">{{ __('Placing order…') }}</span>
                </x-button>
            </x-card>
        </div>
    </div>
</div>
