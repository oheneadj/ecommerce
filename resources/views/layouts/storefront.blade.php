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
        <link rel="stylesheet" href="{{ route('theme.css') }}">
    </head>
    <body class="min-h-screen bg-white text-zinc-900 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        @include('partials.admin-bar')

        <header class="border-b border-zinc-200 dark:border-zinc-700">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2">
                    @if ($store->logo_path)
                        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($store->logo_path) }}" alt="{{ $store->business_name }}" class="h-8 w-auto">
                    @else
                        <span class="text-lg font-semibold text-brand-primary">{{ $store->business_name ?? config('app.name') }}</span>
                    @endif
                </a>

                {{--
                    Hidden below sm: — logo + this + the icon nav don't fit
                    a narrow phone viewport without squeezing the search
                    box down to an unusable sliver (min-w-0 lets it shrink
                    that far). The "Shop" nav icon (magnifying glass) is
                    still a real search entry point on mobile — it lands on
                    /products, which has its own search box in the sidebar.
                --}}
                <div class="hidden min-w-0 flex-1 sm:block">
                    <livewire:storefront.search-autosuggest />
                </div>

                <nav class="flex shrink-0 items-center gap-5 text-sm font-medium">
                    <a href="{{ route('products.index') }}" wire:navigate class="flex items-center gap-1.5 text-zinc-700 transition-colors hover:text-brand-primary dark:text-zinc-300">
                        <x-app-icon name="magnifying-glass" class="size-5" />
                        <span class="hidden sm:inline">{{ __('Shop') }}</span>
                    </a>
                    <a href="{{ route('wishlist.show') }}" wire:navigate class="flex items-center gap-1.5 text-zinc-700 transition-colors hover:text-brand-primary dark:text-zinc-300">
                        <x-app-icon name="heart" class="size-5" />
                        <span class="hidden sm:inline">{{ __('Wishlist') }}</span>
                    </a>
                    @auth
                        <livewire:storefront.notification-indicator />
                    @endauth
                    <livewire:storefront.cart-indicator />

                    @auth
                        <x-dropdown align="end">
                            <x-slot:trigger>
                                <button type="button" class="flex items-center gap-1.5 text-zinc-700 transition-colors hover:text-brand-primary dark:text-zinc-300">
                                    <x-app-icon name="user" class="size-5" />
                                    <span class="hidden sm:inline">{{ __('Account') }}</span>
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
                    @else
                        <a href="{{ route('account.show') }}" wire:navigate class="flex items-center gap-1.5 text-zinc-700 transition-colors hover:text-brand-primary dark:text-zinc-300">
                            <x-app-icon name="user" class="size-5" />
                            <span class="hidden sm:inline">{{ __('Account') }}</span>
                        </a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 dark:border-zinc-700">
            <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-zinc-500 sm:px-6 dark:text-zinc-400">
                @if ($store->tagline)
                    <p class="mb-2">{{ $store->tagline }}</p>
                @endif
                <p class="flex flex-wrap gap-x-4">
                    @if ($store->contact_email)
                        <span>{{ $store->contact_email }}</span>
                    @endif
                    @if ($store->contact_phone)
                        <span>{{ $store->contact_phone }}</span>
                    @endif
                    @if ($store->contact_address)
                        <span>{{ $store->contact_address }}</span>
                    @endif
                </p>
                @if ($footerPages->isNotEmpty())
                    <p class="mt-4 flex flex-wrap gap-x-4">
                        @foreach ($footerPages as $footerPage)
                            <a href="{{ route('pages.show', $footerPage) }}" wire:navigate class="transition-colors hover:text-brand-primary">{{ $footerPage->title }}</a>
                        @endforeach
                    </p>
                @endif
            </div>
        </footer>

        <x-toast-container />
        <x-cookie-consent-banner />
    </body>
</html>
