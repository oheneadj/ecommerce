<x-layouts::storefront :title="__('My Account')">
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold">{{ __('Welcome back') }}{{ auth()->user()->name ? ', '.auth()->user()->name : '' }}</h1>
        </div>

        <x-card>
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-medium">{{ __('Recent orders') }}</h2>
                <a href="{{ route('account.orders') }}" wire:navigate class="text-sm font-medium text-brand-primary hover:underline">{{ __('View all') }}</a>
            </div>

            @if ($orders->isEmpty())
                <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">{{ __("You haven't placed any orders yet.") }}</p>
            @else
                <div class="mt-4 divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($orders as $order)
                        <a href="{{ route('account.orders.show', $order) }}" wire:navigate class="flex items-center justify-between gap-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800">
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
            <h2 class="text-lg font-medium">{{ __('Addresses') }}</h2>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Manage the addresses used at checkout.') }}</p>
            <a href="{{ route('account.addresses') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-brand-primary hover:underline">
                {{ __('Manage addresses') }} &rarr;
            </a>
        </x-card>

        <x-card>
            <h2 class="text-lg font-medium">{{ __('Account settings') }}</h2>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Update your name, email, password, and two-factor authentication.') }}</p>
            <a href="{{ route('profile.edit') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-brand-primary hover:underline">
                {{ __('Manage account settings') }} &rarr;
            </a>
        </x-card>
    </div>
</x-layouts::storefront>
