{{--
    Fixed bottom tab bar — mobile-only primary navigation (Home/Shop/
    Wishlist/Account), added alongside the top row's Bell/Cart so a
    customer never has to scroll up to reach the main destinations, per
    the "add a button navigation for mobile" request. Same icons the
    desktop top nav uses, kept consistent rather than picking new ones.
--}}
@php
    $tabs = [
        ['route' => 'home', 'icon' => 'home', 'label' => __('Home')],
        ['route' => 'products.index', 'icon' => 'squares-2x2', 'label' => __('Shop')],
        ['route' => 'wishlist.show', 'icon' => 'heart', 'label' => __('Wishlist')],
        ['route' => 'account.show', 'icon' => 'user', 'label' => __('Account')],
    ];
@endphp
<nav
    class="fixed inset-x-0 bottom-0 z-30 flex items-center justify-around border-t border-zinc-200 bg-white py-2 sm:hidden dark:border-zinc-700 dark:bg-zinc-900"
    aria-label="{{ __('Primary') }}"
>
    @foreach ($tabs as $tab)
        @php $isActive = request()->routeIs($tab['route']); @endphp
        <a
            href="{{ route($tab['route']) }}"
            wire:navigate
            @if ($isActive) aria-current="page" @endif
            class="flex flex-col items-center gap-0.5 px-3 py-1 text-xs font-medium {{ $isActive ? 'text-brand-primary' : 'text-zinc-500 dark:text-zinc-400' }}"
        >
            <x-app-icon :name="$tab['icon']" :filled="$isActive" class="size-6" />
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
