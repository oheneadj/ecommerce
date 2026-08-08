{{--
    Shared public-facing layout for customer pages (account, cart, checkout,
    wishlist, address, and eventually the storefront itself). Every visual
    per-deployment difference (colors, logo, business name, contact info)
    comes from StoreSetting, never hardcoded here — that's the whole point
    of this being a template reused across separate store deployments.
--}}
@php
    $store ??= \App\Models\StoreSetting::current();
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
                <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2">
                    @if ($store->logo_path)
                        <img src="{{ Illuminate\Support\Facades\Storage::disk('public')->url($store->logo_path) }}" alt="{{ $store->business_name }}" class="h-8 w-auto">
                    @else
                        <span class="text-lg font-semibold text-brand-primary">{{ $store->business_name ?? config('app.name') }}</span>
                    @endif
                </a>

                <nav class="flex items-center gap-4 text-sm font-medium">
                    <a href="{{ route('cart.show') }}" wire:navigate class="text-zinc-700 hover:text-brand-primary dark:text-zinc-300">{{ __('Cart') }}</a>
                    <a href="{{ route('account.show') }}" wire:navigate class="text-zinc-700 hover:text-brand-primary dark:text-zinc-300">{{ __('Account') }}</a>
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
            </div>
        </footer>

        <x-toast-container />
    </body>
</html>
