<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <nav aria-label="{{ __('Account') }}" class="space-y-1">
            @foreach ([
                'account.show' => __('Dashboard'),
                'account.orders' => __('Orders'),
                'account.notifications' => __('Notifications'),
                'account.addresses' => __('Addresses'),
                'wishlist.show' => __('Wishlist'),
                'profile.edit' => __('Profile'),
                'security.edit' => __('Security'),
                'appearance.edit' => __('Appearance'),
            ] as $route => $label)
                <a
                    href="{{ route($route) }}"
                    wire:navigate
                    class="block rounded-lg px-3 py-1.5 text-sm {{ request()->routeIs($route === 'account.orders' ? 'account.orders*' : $route) ? 'bg-zinc-200 font-medium text-zinc-900 dark:bg-zinc-700 dark:text-white' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800' }}"
                >
                    {{ $label }}
                </a>
            @endforeach
        </nav>
    </div>

    <hr class="w-full border-zinc-200 md:hidden dark:border-zinc-700" />

    <div class="flex-1 self-stretch max-md:pt-6">
        {{ $slot }}
    </div>
</div>
