<x-layouts::storefront :title="__('My Account')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">{{ __('Welcome back') }}{{ auth()->user()->name ? ', '.auth()->user()->name : '' }}</h1>
        </div>

        <x-card>
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-lg font-medium">
                    <x-app-icon name="shopping-bag" class="size-5 text-zinc-400" />
                    {{ __('Recent orders') }}
                </h2>
                <x-button href="{{ route('account.orders') }}" wire:navigate variant="ghost">{{ __('View all') }}</x-button>
            </div>

            @if ($orders->isEmpty())
                <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __("You haven't placed any orders yet.") }}</p>
            @else
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($orders as $order)
                        <a href="{{ route('account.orders.show', $order) }}" wire:navigate class="-mx-2 flex items-center justify-between gap-4 rounded-lg px-2 py-3 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            <div>
                                <p class="font-medium">{{ $order->order_number }}</p>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ $order->created_at->format('d M Y') }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-medium">{{ $order->grand_total_formatted }}</span>
                                <x-status-badge :color="$order->status->getColor()">{{ $order->status->getLabel() }}</x-status-badge>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-card>

        <x-card>
            <h2 class="flex items-center gap-2 text-lg font-medium">
                <x-app-icon name="home" class="size-5 text-zinc-400" />
                {{ __('Addresses') }}
            </h2>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Manage the addresses used at checkout.') }}</p>
            <x-button href="{{ route('account.addresses') }}" wire:navigate variant="outline" class="mt-4">
                {{ __('Manage addresses') }}
            </x-button>
        </x-card>

        <x-card>
            <h2 class="flex items-center gap-2 text-lg font-medium">
                <x-app-icon name="cog" class="size-5 text-zinc-400" />
                {{ __('Account settings') }}
            </h2>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Update your name, email, password, and two-factor authentication.') }}</p>
            <x-button href="{{ route('profile.edit') }}" wire:navigate variant="outline" class="mt-4">
                {{ __('Manage account settings') }}
            </x-button>
        </x-card>
    </div>
</x-layouts::storefront>
