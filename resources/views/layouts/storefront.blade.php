{{--
    Shared public-facing layout for customer pages (account, cart, checkout,
    wishlist, address, and eventually the storefront itself). Every visual
    per-deployment difference (colors, logo, business name, contact info)
    comes from StoreSetting, never hardcoded here — that's the whole point
    of this being a template reused across separate store deployments.
--}}
@php
    $store ??= \App\Models\StoreSetting::current();
    $footerPages = \App\Models\StaticPage::query()->where('is_published', true)->orderBy('title')->get();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased">
        @include('partials.admin-bar')

        <header class="border-b border-zinc-200">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2">
                    @if ($store->logo_path)
                        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($store->logo_path) }}" alt="{{ $store->business_name }}" class="h-8 w-auto">
                    @else
                        <span class="text-lg font-semibold text-brand-primary">{{ $store->business_name ?? config('app.name') }}</span>
                    @endif
                </a>

                {{-- Full width at sm:+; on mobile it moves to its own row below (see the second header row). --}}
                <div class="hidden min-w-0 flex-1 sm:block">
                    <livewire:storefront.search-autosuggest />
                </div>

                {{--
                    Shop/Wishlist/Account move to the bottom tab bar on
                    mobile (their own dedicated navigation surface, per
                    "add a button navigation for mobile") — kept here,
                    with labels, at sm:+ where there's room and no bottom
                    bar exists. Bell/Cart stay in the top row at every
                    width since they're quick-access-with-preview-dropdown
                    controls, not primary destinations.
                --}}
                <nav class="flex shrink-0 items-center gap-5 text-sm font-medium">
                    <a href="{{ route('products.index') }}" wire:navigate class="hidden items-center gap-1.5 text-zinc-700 transition-colors hover:text-brand-primary sm:flex">
                        <x-app-icon name="squares-2x2" class="size-5" />
                        <span>{{ __('Shop') }}</span>
                    </a>
                    <a href="{{ route('wishlist.show') }}" wire:navigate class="hidden items-center gap-1.5 text-zinc-700 transition-colors hover:text-brand-primary sm:flex">
                        <x-app-icon name="heart" class="size-5" />
                        <span>{{ __('Wishlist') }}</span>
                    </a>
                    @auth
                        <livewire:storefront.notification-indicator />
                    @endauth
                    <livewire:storefront.cart-indicator />

                    @auth
                        <div class="hidden sm:block">
                            <x-dropdown align="end">
                                <x-slot:trigger>
                                    <button type="button" class="flex items-center gap-1.5 text-zinc-700 transition-colors hover:text-brand-primary">
                                        <x-app-icon name="user" class="size-5" />
                                        <span>{{ __('Account') }}</span>
                                    </button>
                                </x-slot:trigger>

                                <x-menu-item :href="route('account.show')" icon="home" wire:navigate>
                                    {{ __('My Account') }}
                                </x-menu-item>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-menu-item type="submit" icon="arrow-right-start">
                                        {{ __('Log out') }}
                                    </x-menu-item>
                                </form>
                            </x-dropdown>
                        </div>
                    @else
                        <a href="{{ route('account.show') }}" wire:navigate class="hidden items-center gap-1.5 text-zinc-700 transition-colors hover:text-brand-primary sm:flex">
                            <x-app-icon name="user" class="size-5" />
                            <span>{{ __('Account') }}</span>
                        </a>
                    @endauth
                </nav>
            </div>

            {{-- Full-width, properly-sized search row — mobile only. --}}
            <div class="border-t border-zinc-200 px-4 py-3 sm:hidden">
                <livewire:storefront.search-autosuggest />
            </div>
        </header>

        {{--
            pb-24 reserves room for the fixed bottom tab bar below so the
            last bit of page content is never hidden behind it — sm:pb-0
            since the bar itself doesn't exist at sm:+.
        --}}
        <main class="mx-auto max-w-6xl px-4 py-8 pb-24 sm:px-6 sm:pb-8">
            {{ $slot }}
        </main>

        @include('partials.mobile-bottom-nav')

        <footer class="border-t border-zinc-200">
            <div class="mx-auto grid max-w-6xl gap-8 px-4 py-10 text-sm text-zinc-500 sm:grid-cols-3 sm:px-6">
                <div>
                    <div class="flex items-center gap-2">
                        @if ($store->logo_path)
                            <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($store->logo_path) }}" alt="{{ $store->business_name }}" class="h-7 w-auto">
                        @else
                            <span class="text-base font-semibold text-zinc-900">{{ $store->business_name ?? config('app.name') }}</span>
                        @endif
                    </div>
                    @if ($store->tagline)
                        <p class="mt-3">{{ $store->tagline }}</p>
                    @endif
                    @if ($store->socialLinks() !== [])
                        <p class="mt-4 flex flex-wrap gap-3">
                            @foreach ($store->socialLinks() as $platform => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ ucfirst($platform) }}" class="transition-colors hover:text-brand-primary">
                                    <x-app-icon :name="$platform" class="size-5" />
                                </a>
                            @endforeach
                        </p>
                    @endif
                </div>

                <div>
                    <p class="font-medium text-zinc-900">{{ __('Shop') }}</p>
                    <ul class="mt-3 space-y-2">
                        <li><a href="{{ route('products.index') }}" wire:navigate class="transition-colors hover:text-brand-primary">{{ __('All products') }}</a></li>
                        <li><a href="{{ route('wishlist.show') }}" wire:navigate class="transition-colors hover:text-brand-primary">{{ __('Wishlist') }}</a></li>
                        <li><a href="{{ route('account.show') }}" wire:navigate class="transition-colors hover:text-brand-primary">{{ __('My account') }}</a></li>
                        @foreach ($footerPages as $footerPage)
                            <li><a href="{{ route('pages.show', $footerPage) }}" wire:navigate class="transition-colors hover:text-brand-primary">{{ $footerPage->title }}</a></li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <p class="font-medium text-zinc-900">{{ __('Contact') }}</p>
                    <ul class="mt-3 space-y-2">
                        @if ($store->contact_email)
                            <li>{{ $store->contact_email }}</li>
                        @endif
                        @if ($store->contact_phone)
                            <li>{{ $store->contact_phone }}</li>
                        @endif
                        @if ($store->contact_address)
                            <li>{{ $store->contact_address }}</li>
                        @endif
                    </ul>
                </div>
            </div>

            <div class="border-t border-zinc-200 px-4 py-4 text-center text-xs text-zinc-400 sm:px-6">
                &copy; {{ now()->year }} {{ $store->business_name ?? config('app.name') }}. {{ __('All rights reserved.') }}
            </div>
        </footer>

        <x-whatsapp-chat-bubble :store="$store" />
        <x-toast-container />
        <x-cookie-consent-banner />
    </body>
</html>
